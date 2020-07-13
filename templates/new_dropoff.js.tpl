// All the JS for new-dropoff.tpl

// Email regex
var emailFormatRegex = /(\S+@\S+)/;
var emailBracketRegex = /<(\S+@\S+)>/;
var emailRegex = {$validEmailRegexp};

var presetToName = "{$recipName_1}";
var presetToEmail = "{$recipEmail_1}";

var maxFileSize = '{$maxBytesForFileInt}';
var maxTotalSize = '{$maxBytesForDropoffInt}';
var maxChecksumSize = '{$maxBytesForChecksum}';
var maxEncryptSize = '{$maxBytesForEncrypt}';
var minPassphraseLength = '{$minPassphraseLength}';

var processingText = "{t}Scanning for viruses...{/t}";

// Is the password dialog or the addRecipient dialog being displayed?
var showingPasswordDialog = false;
var tryingToUpload = false;
var ZendTo = new Object();

// Library information
var usingLibrary = '{$usingLibrary}';
var library = {$library};

var recipient_id = 1;
var numRecips = 0;
// The number of files we have, which will be in range 1..n
var num_files = 0;
var totalbytes = 0;
var file_objects = [];
var file_descs = [];
var fileInLibrary = []; // true => file is library, false => file is upload
var maxNoteLength = {$maxNoteLength};
var noteLength = 0;
var checksumUnticked = false;

// Does this browser support drag and drop file selection?
var isAdvancedUpload = function() {
  var div = document.createElement('div');
  return (('draggable' in div) ||
          ('ondragstart' in div && 'ondrop' in div)) &&
         'FormData' in window && 'FileReader' in window;
}();

// These must be here so Smarty can substitute values
var addressbook = {$addressbook};
var deleteText = '{t}Delete{/t}';

var ignore_unload = true;



function updateNoteLength() {
  noteLength = $('#note').val().length;
  var left = maxNoteLength - noteLength;
  if (left < 0) {
    $('#noteLengthText').text('{t}__CHARS__ too long{/t}'.replace('__CHARS__', (0-left)));
    $('#noteLengthText').css({ "color":"red", "font-weight":"bold" });
  } else {
    $('#noteLengthText').text('{t}__CHARSLEFT__ / __MAXLENGTH__ left{/t}'.replace('__CHARSLEFT__', left).replace('__MAXLENGTH__', maxNoteLength));
    $('#noteLengthText').css({ "color":"black", "font-weight":"normal" });
  }
}

function addFilesButtonClick(e) {
  $("#AddFiles").click();
  e.preventDefault(); // stop navigation to #
}

function hideEncryptChars() {
  if (this.checked) {
    $('#encryptPassword1').attr('type', 'password');
    $('#encryptPassword2').attr('type', 'password');
  } else {
    $('#encryptPassword1').attr('type', 'text');
    $('#encryptPassword2').attr('type', 'text');
  }
}

// Check if the password is good enough.
// Return a string to display in the error box, or else return ''
function checkPassword() {
  one = $('#encryptPassword1').val();
  two = $('#encryptPassword2').val();
  if (one === undefined) one = '';
  if (two === undefined) two = '';
  length1 = one.length;
  length2 = two.length;
  longer  = (length1>length2)?length1:length2; // max(length1,length2)
  // They have typed something, but not enough
  if (longer>0 && longer<minPassphraseLength)
    return "{t}Too short!{/t}";
  if (one !== two)
    return "{t}Entries do not match{/t}";
  return '';
}

// Return true if they haven't entered anything for the password at all
// function checkPassword() above will return '' (ie success) if the
// password entries are both blank. It's used for UI validation.
function blankPassword() {
  one = $('#encryptPassword1').val();
  two = $('#encryptPassword2').val();
  if (one === undefined) one = '';
  if (two === undefined) two = '';
  length1 = one.length;
  length2 = two.length;
  return (length1+length2>0)?false:true;
}

var dndcollection = $(); // Keeps track of when we're in and out

function dragStart(e) {
  e.originalEvent.dataTransfer.effectAllowed = 'copy';
}

function dragOver(e) {
  e.originalEvent.dataTransfer.dropEffect = 'copy';
  if (dndcollection.length === 0) {
    $('#overlay').fadeIn('fast');
  }
  dndcollection = dndcollection.add(e.target);
}

function dragLeave(e) {
  setTimeout(function() {
    dndcollection = dndcollection.not(e.target);
    if (dndcollection.length === 0) {
      $('#overlay').fadeOut('fast');
    }
  }, 1);
}

function dragDrop(e) {
  dndcollection = $();
  $('#overlay').toggle(false); // Drop, so don't fade out slowly
  if (e.originalEvent && e.originalEvent.dataTransfer &&
      e.originalEvent && e.originalEvent.dataTransfer.files) {
    addFiles(e.originalEvent.dataTransfer.files);
  }
}

// Setup all the Drag-n-Drop stuff. We need it again if upload failed.
function setupDragDrop() {
  $('#AddFilesButton').text('{t}Click to Add Files or Drag Them Here{/t}');
  $(window).on('drag dragstart dragend dragover dragenter dragleave drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
  })
  .on('dragstart',          dragStart)
  .on('dragover dragenter', dragOver)
  .on('dragleave dragend',  dragLeave)
  .on('drop', dragDrop);
}


//
// When document is all loaded, set it up
//
$(document).ready(function(){
  // Setup the address book autocompletion
  addAddressBook();

  function doBeforeUnload() {
    var msg="{t}If you leave this page, you will abandon this drop-off.{/t}";
    if (ignore_unload) return; // Let the page unload
    if (window.event)
      window.event.returnValue = msg; // IE
    else
      return msg; // FF
  }

  if (window.body)
    window.body.onbeforeunload = doBeforeUnload; // IE
  else
    window.onbeforeunload = doBeforeUnload; // FF
  if (presetToName != "" && presetToEmail != "") {
    // If the preset fields are set, add the recipient.
    addRecipient(presetToName, presetToEmail);
  }

  // Check to see if maxFileSize & maxTotalFileSize is set.
  // If so we need to convert to ints from strings.
  if(maxFileSize != '')
    maxFileSize = parseInt(maxFileSize);
  else
    maxFileSize = 0;
  if(maxTotalSize != '')
    maxTotalSize = parseInt(maxTotalSize);
  else
    maxTotalSize = 0;
  if(maxChecksumSize != '')
    maxChecksumSize = parseInt(maxChecksumSize);
  else
    maxChecksumSize = 0;
  if(maxEncryptSize != '')
    maxEncryptSize = parseInt(maxEncryptSize);
  else
    maxEncryptSize = 0;
  if(minPassphraseLength != '')
    minPassphraseLength = parseInt(minPassphraseLength);
  else
    minPassphraseLength = 1;

  // Set the event handlers for the initial file selectors
  // Not needed after all. $("#AddFilesButton").off("click");
  $("#AddFilesButton").on("click", addFilesButtonClick);

  // What of the tickboxes do we show them? This doesn't affect
  // their functionality at all, we just hide them from the UI.
  if (! {$showEncryptCheckbox})
    $('.newdropoff-encryptSpan').css('display', 'none');
  if (! {$showChecksumCheckbox})
    $('.newdropoff-checksumSpan').css('display', 'none');
  if (! {$showConfirmDeliveryCheckbox})
    $('.newdropoff-confirmSpan').css('display', 'none');
  if (! {$showEmailRecipientsCheckbox})
    $('.newdropoff-informSpan').css('display', 'none');
  if (! {$showPasscodeCheckbox})
    $('.newdropoff-informPasscodeSpan').css('display', 'none');
  if (! {$showWaiverCheckbox})
    $('.newdropoff-waiverSpan').css('display', 'none');
  if (! {$defaultNumberOfDaysToRetain|default:'0'}>0) {
    $('.newdropoff-lifetimeSpan').css('display', 'none');
  } else {
    // lifetime box is shown
    $('#lifedays').balloon({
    position: "top center",
    html: false,
    css: { fontSize: '100%', 'max-width': '30vw' },
    contents: '{t}This number can include a decimal point, it does not have to be a whole number.{/t}',
    showAnimation: function (d, c) { this.fadeIn(d, c); }
    });
  }

  // Do we allow them to change either of the email tickboxes?
  // informRecipients and informPasscode
  // Both are allowed and on by default.
  if (! {$allowEmailRecipients}) {
    $('.newdropoff-informSpan').css('display', 'none');
    $('#informRecipients').prop('checked', false).prop('disabled', true);
    $('#informPasscode').prop('checked', false).prop('disabled', true);
    $('#informRecipientsLabel').addClass('disabledtext');
    $('#informPasscodeLabel').addClass('disabledtext');
  }
  if (! {$allowEmailPasscode}) {
    $('.newdropoff-informPasscodeSpan').css('display', 'none');
    $('#informPasscode').prop('checked', false).prop('disabled', true);
    $('#informPasscodeLabel').addClass('disabledtext');
  }
{* }  if (! {$allowRecipWaiver}) {
    $('.newdropoff-waiverSpan').css('display', 'none');
    $('#recipWaiver').prop('checked', false).prop('disabled', true);
    $('#recipWaiverLabel').addClass('disabledtext');
  }
*}
  if (! {$defaultEmailRecipients}) {
    $('#informRecipients').prop('checked', false);
    $('#informPasscode').prop('checked', false);
  }
  if (! {$defaultConfirmDelivery}) {
    $('#confirmDelivery').prop('checked', false);
  }
  if (! {$defaultEmailPasscode}) {
    $('#informPasscode').prop('checked', false);
  }
  if (! {$defaultRecipWaiver}) {
    $('#recipWaiver').prop('checked', false);
  }
  if (maxChecksumSize == 0) {
    $('.newdropoff-checksumSpan').css('display', 'none');
    $('#checksumFiles').prop('checked', false).prop('disabled', true);
    $('#checksumFilesLabel').addClass('disabledtext');
  } else {
    // They can untick it always. But can only tick it if total dropoff
    // size is less than maxChecksumSize.
    $('#checksumFiles').on('change', function() {
      // Did they try to tick it with a huge drop-off?
      if ($(this).is(':checked')) {
        if (totalbytes > maxChecksumSize) {
          // Too big, so untick it and yell at them.
          $('#checksumFiles').prop('checked', false);
          alert("{t escape=no}It will take too long to calculate the checksum of your drop-off.{/t}\n{t escape=no}Only drop-offs up to a maximum of __MAXSIZE__ can be checksummed.{/t}".replace('__MAXSIZE__', formatFileSize(maxChecksumSize)));
        }
      } else {
        // They manually unticked it, so remember not to auto-tick it again
        checksumUnticked = true;
      }
    });
  }
  if (maxEncryptSize == 0 || {$enforceEncrypt}) {
    {* If encryption is enforced and the passphrase has already been set
       by a request for a drop-off, hence allowPassphraseDialog = FALSE,
       then always show the encryption tickbox
       regardless, as it's an external user and they'll want it obvious
       it's being encrypted. *}
  {if $allowPassphraseDialog}
    $('.newdropoff-encryptSpan').css('display', 'none');
    $('#encryptFilesLabel').addClass('disabledtext');
  {else}
    $('#encrypt-balloon').balloon({
      position: "top left",
      html: false,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: '{t 1=#OrganizationShortType#}Every file will be encrypted. This has been enforced by %1, you do not need to do anything.{/t}',
      showAnimation: function (d, c) { this.fadeIn(d, c); }
    });
  {/if}
    $('#encryptFiles').prop('checked', {$enforceEncrypt}).prop('disabled', true);
  }
  if ({$allowEmailRecipients} && {$allowEmailPasscode}) {
    // Uncheck informPasscode if user unchecks informRecipients
    $('#informRecipients').on('change', function() {
      if ($(this).is(':checked')) {
        // Tick informPasscode is they tick informRecips and default is yes
        if ({$defaultEmailPasscode})
          $('#informPasscode').prop('checked', true);
      } else {
        // Untick informPasscode if they untick informRecips
        $('#informPasscode').prop('checked', false);
      }
    });
    // Check informRecipients is user checks informPasscode
    $('#informPasscode').on('change', function() {
      if ($(this).is(':checked')) {
        // Tick informRecips if they tick informPasscode
        $('#informRecipients').prop('checked', true);
      }
    });
  }
  $('#lifedays').val({$lifedays});

  // Now show the checkboxes, as they are all configured
  $('#newdropoff-checkboxes').css('visibility', 'visible');

  // Hide the files and submit button until we've got some files
  $('#uploadTable').toggle(false);
  $('#DropoffButton').toggle(false);

  // If we have drag-n-drop (dnd) support, set it up
  if (isAdvancedUpload) setupDragDrop();

  // Setup the counter & initial value for the note length
  $('#note').on("keyup", updateNoteLength);
  updateNoteLength();

  // Live event for removing recipients.
  // Live allows us to bind to any instance of .emailButton a.remove
  // now or in the future (even if the element doesn't exist yet.)
  $(document).on("click", ".emailButton a.remove", function(){
          $(this).parent().remove();
          if (numRecips > 0) numRecips--;
  });
        
  // If the user hits enter in this field, add the recipient.
  $(document).on("keyup", '#recipEmail', function(e) {
          if(e.keyCode == 13) addSingleRecipient();
  });
  $(document).on("keyup", '#recipName', function(e) {
          if(e.keyCode == 13) addSingleRecipient();
  });
 
  // If they typed an email address into the name field,
  // move it to the email field automatically.
  $(document).on('change', '#recipName', function(e) {
    // Must look ike an email address
    var em = this.value;
    if ((emailFormatRegex.test(em) ||
         emailBracketRegex.test(em)) &&
         $('#recipEmail').val() === '') {
      var r = emailBracketRegex.exec(em);
      if (r !== null) {
        em = r[1];
      }
      $('#recipEmail').val(em);
      $(this).val('');
    }
  });

  $('#addRecipients').on('click', function(){
          showingPasswordDialog = false;
          $.facebox(ZendTo.addRecipients);
          addAddressBook();
  });

  $(document).on('click', '#addMultipleRecipients', function(){
          addMultiple();
          return false;
  });
        
  $(document).on('click', '#showSingleDialog, #showMultipleDialog', function(){
          switch($(this).attr('id')){
                  case "showSingleDialog":
                          $('#showSingleDialog').removeClass('greyButton');
                          $('#showMultipleDialog').addClass('greyButton');
                          
                          $( "#sendMultiple" ).fadeOut(200, function(){
                                  $( "#sendSingle" ).fadeIn(200);
                          });
                  break;
                  case "showMultipleDialog":
                          $('#showMultipleDialog').removeClass('greyButton');
                          $('#showSingleDialog').addClass('greyButton');        
                          
                          $('#sendSingle').fadeOut(200, function(){
                                  $( "#sendMultiple" ).fadeIn(200);
                          });
                  break;
          }
  });
        
  // Get the new recipients form and copy it into a new object,
  // then remove it from the DOM.
  ZendTo.addRecipients = $('#addNewRecipient').html();
  $('#addNewRecipient').remove();

  //
  // Encryption checkbox support
  //
  //
  // This is all totally disabled if they aren't allowed to enter
  // a passphrase or anything, which will happen if this drop-off
  // is the result of a request that had a passphrase stored in it.
{if $allowPassphraseDialog}
  $(document).on("keyup", '#encryptPassword1', keyEncryptPassword);
  $(document).on("keyup", '#encryptPassword2', keyEncryptPassword);
  $(document).on('change', '#hideEncryptChars', hideEncryptChars);

  $('#encryptFiles').on('change', function() {
    if (this.checked) {
      // Box just ticked
      showingPasswordDialog = true;
      // Did they try to tick it with a huge drop-off?
      if (!{$enforceEncrypt} && maxEncryptSize>0 &&
          totalbytes>maxEncryptSize) {
        // Too big, so untick it, yell at them and exit
        $('#encryptFiles').prop('checked', false);
        alert("{t escape=no}It will take too long to encrypt your drop-off.{/t}\n{t escape=no}Only drop-offs up to a maximum of __MAXSIZE__ can be encrypted.{/t}".replace('__MAXSIZE__', formatFileSize(maxEncryptSize)));
      } else {
        $.facebox(ZendTo.encryptPasswordDialog);
      }
    } else {
      encryptFiles = false;
    }
  });

  // Bind to the reveal of facebox
  $(document).on('reveal.facebox', function(){
    if (showingPasswordDialog) {
      // Set up the password dialog box
      pwd = $('#encryptPassword').val();
      $('#encryptPassword1').attr('type', 'password')
                            .val(pwd)
                            .trigger('focus');
      $('#encryptPassword2').attr('type', 'password')
                            .val(pwd);
      $('#hideEncryptChars').prop('checked', true);
      if (pwd !== '') {
        // Enable OK
        $('#setPasswordButton').prop('disabled', false).removeClass('greyButton');
      } else {
        // Disable OK
        $('#setPasswordButton').prop('disabled', true).addClass('greyButton');
      }
    } else {
      // Set up the add recipient(s) dialog box
      $('#recipName').trigger('focus');
    }
  });

  // Bind an event to my new facebox cancel event
  $(document).on('cancel.facebox', function() {
      // Can always cancel encryption passphrase box,
      // but won't allow submission (if enforcing encrypt)
      // until we have a valid password.
      // Cannot cancel password dialog if forcing encryption
      tryingToUpload = false;
      if (showingPasswordDialog) {
        showingPasswordDialog = false;
        cancelEncryptPassword();
      }
  });

  // Bind an event to facebox's close event
  $(document).on('afterClose.facebox', function(){
    if (showingPasswordDialog && {$enforceEncrypt}) {
      // The were entering a password and they MUST encrypt
      if (blankPassword() || checkPassword() !== '') {
        // We haven't got a good non-blank password,
        // so re-present the password dialog.
        $('#encryptFiles').prop('checked', true).trigger('change');
      } else {
        // We have now got a good password, so if they are trying
        // to hit the Upload button, then try it again as it should
        // succeed this time. This will recurse, and we aren't
        // clearing up, but at the end of the upload all of this
        // gets thrown away anyway.
        if (tryingToUpload) submitform();
      }
    } else {
      // Focus on the 'note' element
      $('#note').trigger('focus');
      showingPasswordDialog = false;
    }
  });
{/if}
  // End of code that only happens if they are allowed to see the
  // passphrase dialog ($allowPassphraseDialog).
  //
  // Get the password form and copy it into a new object + remove it
  ZendTo.encryptPasswordDialog = $('#encryptPasswordDialog').html();
  $('#encryptPasswordDialog').remove();

  // Code for dealing with selecting already uploaded files.
  if (usingLibrary == "1") {
    var fs = $('#file_select');
    populateSelectFileFields(fs);
    fs.on('change', function(){
      var selectedOption = $(this).children('option:selected');
      var selectedFile = selectedOption.val();
      if (selectedFile != "-1") { // Did they actually pick a file?
        var desc = "";
        if (typeof selectedOption.data('info') != "undefined")
          desc = selectedOption.data('info').description;
        num_files++; // Pre-increment so 1st element is num_files[1]
        file_objects[num_files] = selectedFile;
        file_descs[num_files] = desc;
        fileInLibrary[num_files] = true;
        appendNewFile(num_files, selectedFile, desc, -1); // -1=>Library
        updateTotalSize();
        $(this).val("-1");
        $('#uploadTable').toggle(num_files>0?true:false);
        $('#DropoffButton').toggle(num_files>0?true:false);
      }
    });
  } else {
    // Don't show the file selector or surrounding text
    $('#libraryselector').toggle(false);
  }

  // If there isn't a recipient already (we may be the end of a request)
  // then click the "Add Recipient" button for them, to add the
  // first recipient, which saves them 1 click.
  if (recipient_id == 1) {
    $('#addRecipients').trigger('click');
  }

});
// End of document(ready) function!

// List the contents of the library into the file selector
function populateSelectFileFields(target){
  $.each(library, function(i, v){
    $(target).append(
      $('<option>', { html: v.filename, 'val': v.where }).data('info', v)
        );
  });
}

// Display a floating point value to "precision" decimal places
function toFixed(value, precision) {
  var precision = precision || 0,
      power = Math.pow(10, precision),
      absValue = Math.abs(Math.round(value * power)),
      result = (value < 0 ? '-' : '') + String(Math.floor(absValue / power));

  if (precision > 0) {
    var fraction = String(absValue % power),
        padding = new Array(Math.max(precision - fraction.length, 0) + 1).join('0');
    result += '.' + padding + fraction;
  }
  return result;
}

// Return an integer number of bytes in MB to 1 decimal place
function formatFileSize(size){
  if (size<0) {
    // It's a library file so doesn't consume any space at all
    return '{t}Library{/t}';
  }
  if (size>=1048576) {
    return toFixed(size / 1048576, 1) + " {t}MB{/t}";
  }
  kb = toFixed(size / 1024, 1);
  // Make tiny files appear to be non-zero length
  if (size>0 && kb==='0.0') kb = '<0.1';
  return kb + " {t}KB{/t}";
}

// Return an integer number of bytes in MB to 0 decimal place
function formatMaxSize(size){
  if (size>=1048576) {
    return toFixed(size / 1048576, 0) + " {t}MB{/t}";
  }
  kb = toFixed(size / 1024, 1);
  // Make tiny files appear to be non-zero length
  if (size>0 && kb==='0.0') kb = '<0.1';
  return kb + " {t}KB{/t}";
}

// Is this thing a file or a directory?
// Note this code only works on a few browsers right now, but it doesn't
// cause any harm so it's staying. One day it might work, who knows?
// Does work on some Windows browsers, which is handy!
function isFile(f) {
  // Works on some Webkit
  if (typeof(f.webkitGetAsEntry) == "function") {
    return f.webkitGetAsEntry().isFile;
  }
  // Works on some other recent stuff
  if (typeof(f.getAsEntry) == "function") {
    return f.getAsEntry().isFile;
  }
  // We can't tell, so assume it is a file.
  return true;
}

// Add files that the user has chosen from local filesystem.
// This does not include library files.
function addFiles(files) {
  for(var i=0; i<files.length && num_files<{$uploadFilesMax}; i++) {
    var filei = files[i];
    if (filei.size>0 && filei.kind != 'string' && isFile(filei)) {
      num_files++; // Pre-increment so 1st element is num_files[1]
      file_objects[num_files] = filei;
      file_descs[num_files] = '';
      fileInLibrary[num_files] = false;
      appendNewFile(num_files, filei.name, '', filei.size);

      // This is an abandoned version of the inside of the "if" above.
      // I hoped a dir would fail to read with FileReader, but no such luck!
      //// To test if it's a file or a directory,
      //// let's try to read the 1st byte of it.
      //reader = new FileReader();
      //reader.onerror = function() { console.log("error occurred on file "+filei.name); };
      //reader.onabort = function() { console.log("abort occurred on file "+filei.name); };
      //// This uses an IIFE so I can synchronously pass filei
      //// into the handler (which picks it up as f)
      //reader.onload = (function(f) {
      //  // The file load (of just the 1st byte) completed successfully
      //  num_files++; // Pre-increment so 1st element is num_files[1]
      //  file_objects[num_files] = f;
      //  file_descs[num_files] = '';
      //  fileInLibrary[num_files] = false;
      //  appendNewFile(num_files, f.name, '', f.size);
      //})(filei);
      //// Now we've setup the FileReader, try to read just the 1st byte
      //var firstbyte = filei.slice(0, 0);
      //reader.readAsBinaryString(firstbyte);
    }
  }
  
  // Now replace the element with a fresh one. Otherwise
  // we can't add a file then delete it then re-add it as the element
  // still thinks that file is selected, so doesn't do anything.
  var filecopy = $('<input type="file" id="AddFiles" multiple style="display:none" onchange="addFiles(this.files)">');
  $('#AddFiles').replaceWith(filecopy);
  
  $('#uploadTable').toggle(num_files>0?true:false);
  $('#DropoffButton').toggle(num_files>0?true:false);
}

var entityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;',
  '`': '&#x60;',
  '=': '&#x3D;'
};
function escapeHtml (string) {
  return String(string).replace(/[&<>"'`=\/]/g, function (s) {
    return entityMap[s];
  });
}

// Append a new file to the list in the UI
function appendNewFile(n, name, desc, size) {
  // Convert the inputs to usable safe strings
  var sizeString = formatFileSize(size);
  var edesc = escapeHtml(desc);
  // Remove leading sub-directory names and so on, ie everything up
  // to and including the last \ or /. ".*" is greedy.
  var ename = name.replace(/^.*[\\\/]/, '');
  ename = escapeHtml(ename);
  var f = $("<tr>", { 'id': n, 'class': 'file' });
  f.append('<td class="filelabel" for="file_'+n+'">'+n+':</td>');
  f.append('<td class="ndfilename">'+ename+'</td>');
  f.append('<td class="fileSize ndfilesize">'+sizeString+'</td>');
  f.append('<td class="filedesc" for="desc_'+n+'"><input type="text" name="desc_'+n+'" id="desc_'+n+'" onchange="saveDesc('+n+',this)" size="30" value="'+edesc+'"/></td>');
  f.append('<td class="fileremove"><a href="#_js" onclick="removeFile('+n+')"><img src="images/x.png" alt="{t}Remove file{/t}" title="{t}Remove file{/t} '+n+'" border="0" style="vertical-align: middle"></a></td>');

  $('#uploadFiles').append(f);
  updateTotalSize();
}

// The description of a file has changed, so save it
function saveDesc(n, el) {
  file_descs[n] = el.value;
}

// Remove file n from the UI (then the data on completion)
function removeFile(n) {
  if (n<=0 || n>num_files) return; // Out of valid range

  // Delete it from the relevant arrays and remove from the page
  file_objects.splice(n, 1);
  file_descs.splice(n, 1);
  fileInLibrary.splice(n, 1);
  $('#'+n).fadeOut('fast', function() { adjustTable(this, n); });
  return false;
}

// The animation has finished, so really update the table
function adjustTable(row, n) {
  $(row).remove();
  num_files--;

  if (num_files<=0) {
    // No files left, so no redraw of anything
    $('#uploadTable').toggle(false);
    $('#DropoffButton').toggle(false);
    num_files = 0;
    updateTotalSize();
    return;
  }

  // If we deleted the last row, nothing else to do except update totals
  if (n>num_files) {
    updateTotalSize();
    return;
  }

  // Now need to renumber everything from n+1 to num_files+1 down by 1
  $('#uploadFiles tr').each(function() {
    // this is a row
    var rownum = $(this).attr('id');
    var newrow = rownum-1;
    if (rownum<=n) return; // Before the ones we want to move

    $(this).attr('id', newrow);
    $(this).children().each(function() {
      // this is a td cell
      // These children are the 4 <td> elements in the row
      if ($(this).hasClass('filelabel')) {
        $(this).attr('for', 'file_'+newrow).text(newrow+':');
      } else if ($(this).hasClass('filedesc')) {
        $(this).attr('for', 'desc_'+newrow);
        // And update its child that's an "input"
        var inp = $(this).children('input').first();
        $(inp).attr('id', 'desc_'+newrow)
              .attr('name', 'desc_'+newrow)
              .removeAttr('onchange')
              .off('change');
        $(inp).on('onchange', function() { saveDesc(newrow, this); });
      } else if ($(this).hasClass('fileremove')) {
        // Only update its "a" child
        var rem = $(this).children('a').first();
        $(rem).removeAttr('onclick')
              .off('click');
        $(rem).on('click', function() { removeFile(newrow); });
        // And the img inside that
        $(rem).children('img').first().attr('title', '{t}Remove file{/t} '+newrow);
      }
    });
  });
  updateTotalSize();
}

// Update the text showing the total size of all the files.
// Returns total bytes, which may be useful elsewhere.
function updateTotalSize() {
  totalbytes = 0;
  for (var i=1; i<=num_files; i++) {
    if (!fileInLibrary[i]) totalbytes += file_objects[i].size;
  }
  var s = formatFileSize(totalbytes)+" / "+formatMaxSize(maxTotalSize);
  $('#totalSize').text(s);
  // If the drop-off is too big to checksum, untick that box
  // Otherwise auto-tick the box unless the user has touched it.
  if (totalbytes > maxChecksumSize) {
    $('#checksumFiles').prop('checked', false);
  } else if (! checksumUnticked) {
    $('#checksumFiles').prop('checked', true);
  }
  // If the drop-off is too big to encrypt, untick that box
  if (!{$enforceEncrypt} && totalbytes > maxEncryptSize &&
      $('#encryptFiles').prop('checked')) {
    $('#encryptFiles').prop('checked', false);
    alert('{t escape=no}It will now take too long to encrypt your drop-off, so encryption has been disabled. If you want to encrypt your drop-off, reduce your drop-off below __MAXSIZE__ and select "Encrypt" again.{/t}'.replace('__MAXSIZE__', formatFileSize(maxEncryptSize)));
  }
  return totalbytes;
}

//
// Encryption dialog support
//

// Click cancel in the password dialog ==> untick encryption
function cancelEncryptPassword() {
  tryingToUpload = false; // Abandon any ongoing upload attempt
  if ({$enforceEncrypt}) {
    $('#encryptFiles').prop('checked', true);
    $('#encryptPassword1').val("");
    $('#encryptPassword2').val("");
  } else {
    $('#encryptFiles').prop('checked', false);
    $(document).trigger('close.facebox');
  }
}

// Click OK/Set in password dialog ==> save password
function setEncryptPassword() {
  if (!blankPassword() && checkPassword() === '') {
    $('#encryptPassword').val(one);
    $(document).trigger('close.facebox');
  } else {
    $('#setPasswordButton').prop('disabled', true).addClass('greyButton');
    $('#encryptFiles').prop('checked', {$enforceEncrypt});
  }
}

// Any key is typed in the password text boxes.
// Enable/disable OK button if they match/mismatch
function keyEncryptPassword(e) {
  error = checkPassword();
  if (!blankPassword() && error === '') {
    // Valid password
    $('#passwordError').html('&nbsp;');
    // Enable OK
    $('#setPasswordButton').prop('disabled', false).removeClass('greyButton');
    // Save password if they pressed return
    if (e.keyCode == 13) setEncryptPassword();
  } else {
    // Invalid password, reason is in error
    $('#passwordError').text(error);
    // Disable OK
    $('#setPasswordButton').prop('disabled', true).addClass('greyButton');
  }
}

//
// They clicked on "Add & Close" in the Add Recipient dialog
//
function add1RecipientAndClose() {
  if (addSingleRecipient()) {
    $(document).trigger('close.facebox');
    return true;
  } else {
    $("#recipEmail").trigger('focus');
    return false;
  }
}


function addSingleRecipient(){
  var currentName = $('#recipName').val();
  var currentEmail = $('#recipEmail').val();
  return addRecipient(currentName, currentEmail);
}

function addRecipient(currentName, currentEmail){
  currentEmail = $.trim(currentEmail);
  currentName = $.trim(currentName);

  currentName = currentName.replace(/[<>]/g, '');

  // Automatically strip surrounding < and >
  var r = emailBracketRegex.exec(currentEmail);
  if (r !== null) {
    currentEmail = r[1];
  }

  if(emailRegex.test(currentEmail) == false) {
    alert("{t}Please enter a valid email address.{/t}");
    $("#recipEmail").trigger('focus');
    return false;
  }

  if ( currentEmail != "" ) {
    // New data
    var format = currentName + " &lt;" + currentEmail + "&gt;";
    
    var emailTemplate = $("<div>", { 'class':'emailButton', 'html': format });
    
    emailTemplate.append($("<a>", { 'class': 'remove', 'style': 'float:right; top:-3px; position:relative' }).append($('<img>', { src: 'images/swish/minus-circle.png', alt: '{t}Remove selected recipient{/t}' })));
    
    emailTemplate.append($("<input>", { 'type': 'hidden', 'name': 'recipName_' + recipient_id, 'value': currentName }));
    emailTemplate.append($("<input>", { 'type': 'hidden', 'name': 'recipEmail_' + recipient_id, 'value': currentEmail }));
    emailTemplate.append($("<input>", { 'type': 'hidden', 'name': 'recipient_' + recipient_id, 'value': 'recipient_id' }));
    
    emailTemplate.insertBefore('#emailHolder a#addRecipients');
    
    recipient_id++; // This keeps counting ever-upwards, regardless of deletes
    numRecips++; // For ensuring we have more than 0
    
    clearRecipientFields();
    $("#recipName").trigger('focus');
    return true;
  }
  return false;
}

function addMultiple(){
  // Get contents of text field.
  var rawData = $('#multipleRecipients').val();
  var rejectedAddresses = "";
  
  if (rawData.length == 0) return;
  
  // Pull out the lines.
  var lines = rawData.split(/\r\n|\r|\n/);
  
  for (recipient in lines){
    if ( emailFormatRegex.test(lines[recipient]) ) {
      var email = emailFormatRegex.exec(lines[recipient]);
      var thisEmail = email[1];
      var thisName = lines[recipient].replace(thisEmail, "");
      
      if (emailBracketRegex.test(thisEmail)) {
        em = emailBracketRegex.exec(thisEmail);
        thisEmail = em[1];
      }
      thisName = thisName.replace(/[<>]/g, '');
      if (emailRegex.test(thisEmail) == false) {
        rejectedAddresses += lines[recipient] + "\n";
        continue;
      }
      addRecipient(thisName, thisEmail);
    } else {
      rejectedAddresses += lines[recipient] + "\n";        
    }
  }
  $('#multipleRecipients').val(rejectedAddresses);
}


function clearRecipientFields(){
  $('#recipName, #recipEmail').val("");
}

function focus(el){
  $($(el)).trigger('focus');
}

function validateForm()
{
  if ( num_files < 1 ) {
    alert("{t}Please add at least one file first!{/t}");
    return false;
  }
  if ( numRecips < 1 ) {
    // Show the Add Recipients box instead of an alert.
    $('#addRecipients').trigger('click');
    return false;
  }
  if(updateTotalSize() > maxTotalSize){
    alert('{t}Your files are larger than the maximum total size you can send in one drop-off (__MAXTOTALSIZE__).{/t}'.replace('__MAXTOTALSIZE__', formatMaxSize(maxTotalSize)));
    return false;
  }
  for (var i=1; i<=num_files; i++) {
    if (!fileInLibrary[i] && file_objects[i].size > maxFileSize) {
      alert('{t}File __NUMBER__ is larger than the maximum file size you can send in a drop-off (__MAXFILESIZE__).{/t}'.replace('__NUMBER__',i).replace('__MAXFILESIZE__', formatFileSize(maxFileSize)));
      return false;
    }
  }
  var days = Number($('#lifedays').val());
  if ( isNaN(days) || days<0.1 || days>{$keepForDays|default:'1'} ) {
    $('#lifedays').addClass('error-highlight');
    alert('{t 1=$keepForDays}Drop-off expiry time must be a number of days, at most %1.{/t}');
    return false;
  } else {
    $('#lifedays').removeClass('error-highlight');
  }

{* Don't do this check if encryption is enforced but we aren't allowing
   them to set the passphrase, as we're going to get it from a request *}
{if $allowPassphraseDialog}
  // Are we encrypting?
  if ({$enforceEncrypt} || $('#encryptFiles').is(':checked')) {
    // Have they entered nothing, or have they entered a bad password?
    if (blankPassword() || checkPassword() !== '') {
      // Show the password entry dialog
      $('#encryptFiles').prop('checked', true).trigger('change');
      return false;
    }
  }
{/if}
  return true;
}

// Convert the number of a file_object into an array of its metadata
function filemeta2JSON(n) {
  var f = file_objects[n];
  var a = {
    "name": f.name,
    "type": f.type,
    "size": f.size.toString(),
    "tmp_name": n.toString(),
    "error": 0
  };
  return JSON.stringify(a);
}

// Do all the preparatory work needed before starting to upload
// anything to the server. Gets this stuff out of the way.
function setupPosting() {
    var fileSizes = updateTotalSize(); // Need this for progress bar
    // Switch off all the drag and drop functionality
    //$('#container').off('drag dragstart dragend dragover dragenter dragleave drop');
    $('#note').off("keyup", updateNoteLength);
    // Disable all the buttons
    $('#AddFilesButton').prop('disabled', true).addClass('greyButton');
    $('#RealDropoffButton').prop('disabled', true).addClass('greyButton');
    $('#file_select').prop('disabled', true);

    // Set the "processing..." text depending on what we're doing
    checksumming = $('#checksumFiles').is(':checked');
    encrypting   = $('#encryptFiles').is(':checked');
    if (checksumming || encrypting) {
      // Start by shortening it
      processingText = "{t}Scanning{/t}";
      if (checksumming)
        processingText = processingText + ", {t}Checksumming{/t}"
      if (encrypting)
        processingText = processingText + ", {t}Encrypting{/t}"
      processingText = processingText + "..."
    }
    
    showUpload();
    scroll(0,0);
    ignore_unload = false;

    return fileSizes;
}

function submitform() {
  tryingToUpload = true; // Marker so we know what user is doing

  // Form invalid? Then just bail out.
  if (!validateForm())
    return;

  // Do all the pre-amble to setup the UI for uploading,
  // and find the grand total number of bytes of files to
  // upload.
  var grandTotalBytes = setupPosting();
  var totalSentFile   = 0; // total bytes sent in completed files
  var totalSentChunk  = 0; // total bytes sent in completed chunks of current file

  var form = document.forms.namedItem("dropoff");
  var cData = new FormData(form); // This is for chunking & gets pruned
  var oData = new FormData(form); // Want 2 copies of the original!

  //
  // Are we going to attempt uploading files in small chunks?
  // Doing so changes how we do the upload process a *lot*.
  //
  // First need to check we have any non-library files, and what index
  // they start at. Indexes start at 1.
  var firstNonLibraryFile = 0;
  var uploadingInChunks = {$uploadChunkSize}>0?true:false;
  do {
    firstNonLibraryFile++;
  } while (firstNonLibraryFile<=num_files && fileInLibrary[firstNonLibraryFile]);
  // Set it to false if there are no files for upload
  uploadingInChunks = uploadingInChunks &&
                      (firstNonLibraryFile <= num_files);

  if (uploadingInChunks) {
    // Prune all the unwanted data out of the form, as this
    // all gets sent with every chunk otherwise.
    // We'll get it back later with new FormData(form);
    const regexToPrune = RegExp('^desc_|^recipName_|^recipEmail_|^recipient_');
    var keysToDelete = ['Action', 'senderOrganization', 'encryptPassword', 'note', 'subject', 'checksumFiles', 'confirmDelivery', 'informRecipients', 'informPasscode'];
    var formDataEntries = cData.entries(), formDataEntry = formDataEntries.next(), k;
    // This is what you have to do, to work on IE as well
    while (!formDataEntry.done) {
      k = formDataEntry.value[0];
      if (regexToPrune.test(k))
        keysToDelete.push(k);
      formDataEntry = formDataEntries.next();
    }
    // Having found the keys to delete, delete them
    keysToDelete.forEach(function (k) {
      cData.delete(k);
    });

    // But add everything nneded for the final POST
    for (var i=1; i<=num_files; i++) {
      if (fileInLibrary[i]) {
        oData.append('file_select_'+i, file_objects[i]);
      } else {
        // We have already sent the file contents,
        // so just send all the metadata
        oData.append('file_'+i, filemeta2JSON(i));
      }
      oData.append('desc_'+i, file_descs[i]);
    }
    // Add the signal to say we've uploaded chunks already
    oData.append('sentInChunks', 1);

  }

  // This is done after the last chunk is complete.
  // Send the rest of all the form data.
  function sendOtherFormData() {
    $.ajax({
        url: "{$zendToURL}{call name=hidePHPExt t='dropoff.php'}",
        method: 'POST',
        data: oData,
        cache: false,
        contentType: false,
        processData: false,
        complete: function(jqXHR, status) {
          ignore_unload = true;
          if (status == "success") {
            var dropOffPage = $(jqXHR.responseText);
            {if $SMTPdebug}
              // Butt ugly, but you can see the debug output necessary.
              $('#container').html(dropOffPage);
            {else}
              $('#container').html(dropOffPage.children('#container').children('#dropoff-inner'));
            {/if}
            $('.ui-helper-hidden-accessible div').hide();
          } else {
            // The upload failed for some reason
            alert( 'ZendTo upload failed: ' + status );
            console.log( 'ZendTo upload failed: ', status );
            hideUpload();
            alert("{t escape=no}Sorry, I failed to drop-off your files!{/t}\n{t escape=no}Note that you cannot drop-off directories, only files.{/t}");
            // Put the form back into a working state again
            enableFormAgain();
          }
        }
    });
  }

  // This function only called when sending files in small chunks
  function sendFileInChunks(fileNum, fileObj) {
    var chunkSize = {$uploadChunkSize};
    var retries = 0; // Number of retries of this chunk
    var maxRetries = 5; // This should be a prefs setting?
    var reader = new FileReader();
    cData.set('chunkOf', fileNum);
    uploadChunks(0);

    function uploadChunks(start) {
      var nextChunk = start + chunkSize; // OFFBY1: + 1;
      var blob = fileObj.slice( start, nextChunk );
      cData.set('chunkData', blob);

      reader.onloadend = function (e) {
        if ( e.target.readyState !== FileReader.DONE ) {
          return;
        }

        $.ajax( {
          xhr: function() {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener("progress", function(e) {
              // total of all completed files so far,
              // + start location within this file,
              // + data actually sent in this chunk so far
              // Then convert it to a percentahge.
              var pc = Math.floor(((totalSentFile + totalSentChunk + e.loaded)*100)/grandTotalBytes);
              pc = Math.min(pc, 100); // Clip it at max 100%
              //var pc = Math.floor((e.loaded*100)/e.total);
              // pc = Math.min(100, Math.max(0, pc)); // Limit to 0-100 for safety
              var c = (Math.max((pc-95)*3, 0)).toString();
              $('#progressinner').css({ "width": pc.toString()+'%', "border-radius": "15px "+c+"px "+c+"px 15px" });
              if (pc == 100) {
                $('#percentText').text(processingText);
              } else {
                $('#percentText').text("{t}Uploaded: __PERCENT__%{/t}".replace("__PERCENT__", pc.toString()));
              }
            }, false);
            return xhr;
          },
          url: "{$zendToURL}{call name=hidePHPExt t='savechunk.php'}",
          method: 'POST',
          cache: false,
          contentType: false,
          processData: false,
          data: cData,
          error: function (jqXHR, textStatus, errorThrown) {
             // The upload failed for some reason
            console.log( 'ZendTo file chunk upload failed: ',
                         textStatus, errorThrown );
            console.log( 'Response was ' + $(jqXHR.responseText) );
            // Upload of chunk failed, but not totally sure why.
            // There might be something helpful in responseText,
            // but not always. So retry.
            retries++;
            if (retries < maxRetries) {
              // Retry the same chunk
              uploadChunks(start);
            } else {
              // Too many retries
              console.log( 'ZendTo file chunk retried too many times' );
              hideUpload();
              alert("{t escape=no}Sorry, I failed to drop-off your files!{/t}\n{t escape=no}Note that you cannot drop-off directories, only files.{/t}");
              // Put the form back into a working state again
              enableFormAgain();
            }

          },
          success: function( data ) {
            if ( data.substr(0, 7) !== 'Success' ) {
              console.log( 'ZendTo file chunk upload failed:', data );
              // Does the server say we should retry?
              if ( data.substr(0,5) === 'Retry' ) {
                retries++;
                if (retries < maxRetries) {
                  // Send the same chunk again
                  uploadChunks( start );
                } else {
                  hideUpload();
                  // alert(sprintf("{t escape=no}Sorry, I tried %d times to send a section of one of your files, but the transfer never succeeded.{/t}", maxRetries));
                  alert("{t escape=no}Sorry, I tried %d times to send a section of one of your files, but the transfer never succeeded.{/t}".replace('%d', maxRetries));
                  retries = 0;
                  enableFormAgain();
                }
                return;
              }
              // Not told to retry
              hideUpload();
              // alert(sprintf("{t escape=no}Sorry, I could not send your files. The server said %s.{/t}", data));
              alert("{t escape=no}Sorry, I could not send your files. The server said %s.{/t}".replace('%s', data));
              retries = 0;
              enableFormAgain();
              return;
            }
            // Reset counter of retries per chunk
            retries = 0;
            // Move onto the next chunk
            if ( nextChunk < fileObj.size ) {
              totalSentChunk = nextChunk;
              uploadChunks( nextChunk );
            } else {
              // Upload of this file complete.
              totalSentFile = totalSentFile + file_objects[fileNum].size;
              totalSentChunk = 0;
              // Work out next non-library file number
              do {
                fileNum++;
              } while (fileNum<=num_files && fileInLibrary[fileNum]);
              // Have we fallen off the end of the list?
              if (fileNum<=num_files) {
                // No, so start the next one (tail recursive).
                sendFileInChunks(fileNum, file_objects[fileNum]);
              } else {
                sendOtherFormData();
              }
            }
          }
        });
      };

      reader.readAsArrayBuffer(blob);
    }

  }

  if (uploadingInChunks) {
    // Send the contents of each uploaded file
    // (not library ones)
    // Start it all off by sending the 1st non-library file.
    // That uses tail recursion to upload all
    // the rest of the files and then finally all
    // the metadata for the drop-off and its files.
    sendFileInChunks(firstNonLibraryFile, file_objects[firstNonLibraryFile]);

  } else {

    //
    // Uploading everything in 1 go (traditional).
    //
    // Add the objects for each of the files
    var i;
    for (i=1; i<=num_files; i++) {
      if (fileInLibrary[i]) {
        oData.append('file_select_'+i, file_objects[i]);
      } else {
        oData.append('file_'+i, file_objects[i]);
      }
      oData.append('desc_'+i, file_descs[i]);
    }
    // Send all the file data the old fashioned way.
    $.ajax({
        xhr: function() {
          var xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener("progress", function(e) {
            var pc = Math.floor((e.loaded*100)/e.total);
            // pc = Math.min(100, Math.max(0, pc)); // Limit to 0-100 for safety
            var c = (Math.max((pc-95)*3, 0)).toString();
            $('#progressinner').css({ "width": pc.toString()+'%', "border-radius": "15px "+c+"px "+c+"px 15px" });
            if (pc == 100) {
              $('#percentText').text(processingText);
            } else {
              $('#percentText').text("{t}Uploaded: __PERCENT__%{/t}".replace("__PERCENT__", pc.toString()));
            }
          }, false);
          return xhr;
        },
        method: "POST",
        url: "{$zendToURL}{call name=hidePHPExt t='dropoff.php'}",
        // JKF not allowed to do this: headers: { Connection: 'close' },
        data: oData,
        cache: false,
        // contentType: "multipart/form-data",
        contentType: false,
        processData: false,
        // dataType:    'html', // Trying this out
        // This used to never get called by Safari, but does now.
        complete: function(jqXHR, status) {
          ignore_unload = true;
          if (status == "success") {
            var dropOffPage = $(jqXHR.responseText);
            {if $SMTPdebug}
              // Butt ugly, but you can see the debug output necessary.
              $('#container').html(dropOffPage);
            {else}
              $('#container').html(dropOffPage.children('#container').children('#dropoff-inner'));
            {/if}
            $('.ui-helper-hidden-accessible div').hide();
          } else {
            // The upload failed for some reason
            hideUpload();
            alert("{t escape=no}Sorry, I failed to drop-off your files!{/t}\n{t escape=no}Note that you cannot drop-off directories, only files.{/t}");
            // Put the form back into a working state again
            enableFormAgain();
          }
        }
    });
  }
}

function enableFormAgain() {
  $('#uploadTable').toggle(true);
  $('#DropoffButton').toggle(true);
  $('#note').on("keyup", updateNoteLength);
  $('#AddFilesButton').prop('disabled', false)
                      .removeClass('greyButton');
  $('#RealDropoffButton').prop('disabled', false)
                      .removeClass('greyButton');
  $('#file_select').prop('disabled', false);
}

