<?PHP

class DecryptStream {
  private $iv = '';
  private $iv_safecopy = '';
  private $key = '';
  private $in_filename = '';
  private $fd_in;
  private $insize = 0;
  private $chunk_size = 0;
  private $stream;
  private $plainfile_pos = 0; // Just for ftell()
  private $hit_eof = false;
  private $hit_finaltag = false;
  private $encryptedchunk = '';
  private $plainchunk = '';
  private $plainchunk_pos = 1; // >= strlen($plainchunk) ==> must decrypt more data!
  private $plainchunk_length = 0;

  // Moved to NSSUtils.php
  //// De-obfuscate a binary string.
  //private function deobfuscateString( $in ) {
  //  $out = '';
  //  $in = strrev($in);
  //  for ($i=0; $i<strlen($in); $i++) {
  //    if ($i==0)
  //      $out .= $in[$i] ^ 'Z';
  //    else
  //      $out .= $in[$i] ^ $in[$i-1];
  //  }
  //  return $out;
  //}

  // This is called when a stream object is first opened
  function stream_open($path, $mode, $options, &$opath) {
    // Format of $path is a URL.
    // We put the IV (in binhex) in the USERNAME
    //        the passphrase (in binhex) in the PASSWORD
    //        the filename in the PATH
    // The HOST has to be present but is ignored here. Use 'a' or something.
    $url = parse_url($path);

    // Reverse what was done in NSSDropoff.php
    $this->iv = deobfuscateString(sodium_hex2bin($url['user']));
    $this->iv_safecopy = $this->iv; // We need it again if stream rewound
    $password = gzuncompress(deobfuscateString(sodium_hex2bin($url['pass'])));
    $this->in_filename = $url['path'];

    // Bail out if we aren't trying to read from this stream
    if (substr($mode, 0, 1) !== 'r')
      return false;

    // Open the input file, or bail out
    $this->fd_in = fopen($this->in_filename, 'rb');
    if ($this->fd_in === false)
      return false;
    $this->in_size = fstat($this->fd_in)['size'];

    // Read the info about the secret key we stored at the start.
    $alg = unpack('P', fread($this->fd_in, 8))[1];
    $opslimit = unpack('P', fread($this->fd_in, 8))[1];
    $memlimit = unpack('P', fread($this->fd_in, 8))[1];
    $this->chunk_size = unpack('P', fread($this->fd_in, 8))[1]; // JKF Added this
    $this->decrypt_size = unpack('P', fread($this->fd_in, 8))[1]; // JKF Added this
    $salt = fread($this->fd_in, SODIUM_CRYPTO_PWHASH_SALTBYTES);

    // Read the header we wrote next
    $this->header = fread($this->fd_in,
                    SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

    // From that lot, work out the key
    $this->key = sodium_crypto_pwhash(
                   SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
                   $password, $salt, $opslimit, $memlimit, $alg);
    if ($this->key === false)
      return false;

    // Create a stream using the header and the key
    $this->stream = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
                $this->header, $this->key);
    $this->hit_eof = false;
    $this->hit_finaltag = false;
    $this->plainchunk = '';
    $this->plainchunk_length = 0;
    $this->plainchunk_pos = 1;
    $this->plainfile_pos = 0; // How far through the plaintext file are we

    // Try to read and decrypt the start of the file.
    // This way we can immediately fail if it turns out the passphrase
    // was wrong or we can't decrypt the file.
    $result = $this->fetch_new_block();
    return $result;
  }

  // This reads a new block of data from the decyption.
  // Returns true on success, false on failure.
  function fetch_new_block() {
    // Read a new chunk from the encrypted file
    // That ABYTES constant is difference between encrypted length & plain
    $this->encryptedchunk = fread($this->fd_in,
                 $this->chunk_size +
                 SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
    // feof() will say FALSE if you read to exactly the end of the file :(
    $this->hit_eof = (feof($this->fd_in) ||
                      ftell($this->fd_in) === $this->in_size);    

    // Decrypt the next chunk from the stream
    $res = sodium_crypto_secretstream_xchacha20poly1305_pull(
             $this->stream,
             $this->encryptedchunk,
             $this->iv);

    // If it failed, bail out
    if ($res === false) {
      $this->stream_close();
      return false;
    }

    // Got a new decrypted block of data
    list($this->plainchunk, $tag) = $res;
    $this->plainchunk_length = strlen($this->plainchunk);
    $this->plainchunk_pos = 0;
    if ($this->plainchunk_length == 0)
      // If it read nothing, it will return nothing, which is harmless
      $this->hit_eof = true;
    if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL)
      $this->hit_finaltag = true;

    // Only set the IV for the 1st chunk of a file,
    // after that it must be empty.
    if (is_string($this->iv)) sodium_memzero($this->iv);
    $this->iv = '';

    return true;
  }
  

  // Read some data from the stream
  function stream_read($maxbytestoreturn) {
    if ($this->plainchunk_pos >= $this->plainchunk_length) {
      // We have no more decrypted data to return, so must read and
      // decrypt some more first.
      if (!$this->fetch_new_block())
        return '';
    }

    // Now we have at least 1 byte of data to send!
    $data = substr($this->plainchunk,
                   $this->plainchunk_pos,
                   $maxbytestoreturn);
    // It doesn't matter if that falls off the end of plainchunk.
    // If that happens, it will simply return fewer bytes than requested,
    // and the next call to stream_read will decrypt some new data.
    $datalen = strlen($data);
    $this->plainchunk_pos += $datalen;
    $this->plainfile_pos  += $datalen;
    return $data;
  }

  // Close the stream, so clear up
  function stream_close() {
    if (is_resource($this->fd_in))        fclose($this->fd_in);
    if (is_string($this->iv))             sodium_memzero($this->iv);
    if (is_string($this->iv_safecopy))    sodium_memzero($this->iv_safecopy);
    if (is_string($this->key))            sodium_memzero($this->key);
    if (is_string($this->in_filename))    sodium_memzero($this->in_filename);
    if (is_string($this->fd_in))          sodium_memzero($this->fd_in);
    if (is_string($this->key))            sodium_memzero($this->key);
    if (is_string($this->stream))         sodium_memzero($this->stream);
    if (is_string($this->encryptedchunk)) sodium_memzero($this->encryptedchunk);
    if (is_string($this->plainchunk))     sodium_memzero($this->plainchunk);
    $this->fd_in = NULL;
  }

  // How far through the decrypted data are we?
  function stream_tell() {
    return $this->plainfile_pos;
  }

  // Have we hit the end yet?
  function stream_eof() {
    if ($this->plainchunk_pos >= $this->plainchunk_length) {
      // Only true if we've run out of unsent decrypted data,
      // and we've hit the end of the encrypted file.
      return ($this->hit_eof || $this->hit_finaltag);
    } else {
      // Still have decrypted data to send, despite having hit the end
      // of the encrypted file.
      return false;
    }
  }

  function stream_seek( $offset, $whence ) {
    // The only case I handle is for rewind()
    if ($whence === SEEK_SET && $offset === 0) {
      // Reset the decryption process    
      $this->stream = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
                      $this->header, $this->key);
      $this->hit_eof = false;
      $this->hit_finaltag = false;
      $this->plainchunk = '';
      $this->plainchunk_length = 0;
      $this->plainchunk_pos = 1;
      $this->plainfile_pos = 0; // How far through the plaintext file are we

      // As we are restarting the decryption, we need to have the IV
      // in place again.
      $this->iv = $this->iv_safecopy;

      // Reset the encrypted file back to the start, after my header block
      fseek($this->fd_in,
            8*5 + SODIUM_CRYPTO_PWHASH_SALTBYTES + 
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES,
            SEEK_SET);
      return true;
    }
    return false;
  }
}

