{$thisTemplate=$smarty.template}{include file="header.tpl"}
<link rel="stylesheet" href="css/jquery-ui.min.css">
<script type="text/javascript" src="js/jquery.balloon.min.js"></script>
<div id="dropoff-inner">

<script type="text/javascript">
<!--

var allowResend = '{$allowResend}';
var resenddefaultIsYes = {$defaultEmailPasscode};
var waiverAgreed = false;
var resendtheysaidyes = false;
var resendtheysaidno  = false;
var showingResendDialog = false;
var showingPasswordDialog = false;
var wantToDownloadAll = false;
var fidTryingToDecrypt = -1;
var ZendTo = new Object();

{if $isDeleteable}
function doDelete(){
  if ( confirm("{t}Do you really want to delete this dropoff?{/t}") ) {
    return document.deleteDropoff.submit();
  }
  return 0;
}
{/if}

{if $isSendable}

// This is called by the href in the Yes/No buttons in the resend dialog
function resendYesNo(yesno) {
  if (yesno) {
    resendtheysaidyes = true;
    $(document).trigger('close.facebox');
  } else {
    resendtheysaidno = true;
    $(document).trigger('close.facebox');
  }
}

function keyResend(e) {
  if (!showingResendDialog) return false;
  if (e.keyCode == 13) {
    // They pressed Return so do the default
    if (resenddefaultIsYes)
      resendtheysaidyes = true;
    else
      resendtheysaidno = true;
    $(document).trigger('close.facebox');
  }
}

function doResend(){
  {if $allowEmailPasscode=="true"}
    showingPasswordDialog = false;
    showingResendDialog   = true;
    $.facebox(ZendTo.resendDialog);
  {else}
    $('#resendWithPasscode').val("no");
    return document.resendDropoff.submit();
  {/if}
}
{/if}

{if $isClickable && $dropoffFilesCount>0}
function doDownloadZip() {
  // Download all the files as a zip.
  // Just the same as doing one file, just set fid to 'all'.
{if $isEncrypted}
  downloadEncrypted('all');
{else}
  window.location.href = $('#downloadZipLink').attr('href');
{/if}
}


function doDownloadAll() {
  // Find all the download links in the table of files
  var links = $('#filesTable a[name^="download_"]');

  // This is how to download all the rest (tail recursion)
  function downloadNext(i) {
    // Bail out if reached the end of the list
    if (i >= links.length) {
      // But re-enable the buttons first!
      $('#deleteButton').prop('disabled', false).removeClass('greyButton');
      $('#resendButton').prop('disabled', false).removeClass('greyButton');
      $('#downloadAllButton').prop('disabled', false).removeClass('greyButton');
      $('#downloadZipButton').prop('disabled', false).removeClass('greyButton');
      return;
    }

    // Highlight the link we are downloading
    $(links[i]).addClass('downloading');

    if (links[i].click)
      // Most browsers have a click() function
      links[i].click();
    else
      // else back off to the jQuery version
      $(links[i]).click();

    // Pause for 1000ms then start all the rest
    // The pause makes it more likely to work!
    setTimeout(function() {
      // De-highlight the link again
      $(links[i]).removeClass('downloading');
      // Start all the rest (the tail recursive bit)
      downloadNext(i+1);
    }, 1000);
  }

{if $isEncrypted}
  // Find the passphrase if we don't already have one
  if ($('#n').val().length == 0) {
    // No, so display password dialog
    showingPasswordDialog = true;
    showingResendDialog   = false;
    $('#decryptPassword').val('');
    wantToDownloadAll = true;
    $.facebox(ZendTo.passwordDialog);
    return;
  }
{/if}  

  // Stop us looping if something fails
  wantToDownloadAll = false;
  // First disable the buttons on the page
  $('#deleteButton').prop('disabled', true).addClass('greyButton');
  $('#resendButton').prop('disabled', true).addClass('greyButton');
  $('#downloadAllButton').prop('disabled', true).addClass('greyButton');
  $('#downloadZipButton').prop('disabled', true).addClass('greyButton');
  // Then kick it all off by starting to download the first one
  downloadNext(0);
}
{/if}

{if $isEncrypted}
// Download file fid f
function downloadEncrypted(f) {
  // Have we got the password yet?
  // Need to remember which file they clicked so we can come back to it.
  // If we are called with -1, just try to get the last file we tried.
  // Need to catch zero explicitly, as otherwise 'all'==0.
  if (f==='all' || f===0 || f>0) fidTryingToDecrypt = f;

  // Do we have a confirmed password?
  if ($('#n').val()) {
    // We have a password, so try to download fid
    $('#fid').val(fidTryingToDecrypt);
    // Warn Mac users that big zip files won't work in
    // Archive Utility, so they'll have to use the "unzip"
    // command in a Terminal window.
    // Zips over this size have to be built with Zip64 extensions.
    if (fidTryingToDecrypt==='all' &&
        navigator.platform.toUpperCase().indexOf('MAC')>=0 &&
        ({$dropoffFilesCount}-0 > 65500 || {$totalBytes}-0 > 4000000000)) {
      alert("{t}Warning: Zip files of this size will probably not open on a Mac by double-clicking them. You will have to use another app, or else the 'unzip' or 'ditto' commands in a Terminal window.{/t}");
    }
    document.decryptDropoff.submit();
  } else {
    // No, so display password dialog
    showingPasswordDialog = true;
    showingResendDialog   = false;
    $('#decryptPassword').val('');
    $.facebox(ZendTo.passwordDialog);
  }
  return false;
}

function hideDecryptChars() {
  if (this.checked) {
    $('#decryptPassword').attr('type', 'password');
  } else {
    $('#decryptPassword').attr('type', 'text');
  }
  $('#decryptPassword').trigger('focus');
}

// Click cancel in the password dialog ==> don't save password
function cancelDecryptPassword() {
  $(document).trigger('close.facebox');
}

// Click OK in password dialog ==> save password
function setDecryptPassword() {
  $('#n').val($('#decryptPassword').val());
  $(document).trigger('close.facebox');
  $('#error').remove();
}

// Any key is typed in the password text box.
function keyDecryptPassword(e) {
  if ($('#decryptPassword').val()) {
    // Enable OK
    $('#setPasswordButton').prop('disabled', false).removeClass('greyButton');
    // Save password if they pressed return
    if (e.keyCode == 13) setDecryptPassword();
  } else {
    // Disable OK
    $('#setPasswordButton').prop('disabled', true).addClass('greyButton');
  }
}


{/if}


$(document).ready(function() {
  {* If we came from new drop-off form, disable the language picker *}
  {if ! $isClickable && ! $isSendable && ! $isDeleteable}
    $('#localeButton').prop('onclick', null);
    $('#localeButton').removeClass('dropdown-has-hover');
  {/if}

  {if $isDeleteable}
    $('#deleteButton').on('click', doDelete);
  {/if}
  {if $isSendable}
    $('#resendButton').on('click', doResend);
  {/if}
  {if $isClickable && $dropoffFilesCount>0}
    {* FLEX $('#downloadAllDiv').css('display', 'inline'); *}
    $('#downloadAllDiv').css('display', 'flex');
    $('#downloadZipButton').on('click', doDownloadZip);
    // If we are not on IE, enable and show "download all" button
    if (! /MSIE\s|Trident\//i.test(navigator.userAgent)) {
      $('#downloadAllButton').on('click', doDownloadAll);
      {* FLEX $('#downloadAllButtonSpan').css('display', 'inline'); *}
      $('#downloadAllButton').css('display', 'inline');
    }
  {/if}

  //
  // Now set up the facebox dialogs
  //

{if $isEncrypted}
  $(document).on("change keyup input", '#decryptPassword', keyDecryptPassword);
  $(document).on('change', '#hideDecryptChars', hideDecryptChars);
{/if}
{if $isSendable}
  $(document).on('keyup', '#resendDialog', keyResend);
{/if}

  // Bind to the reveal of facebox
  $(document).on('reveal.facebox', function(){
{if $isSendable}
    if (showingResendDialog) {
      // Set up the resend dialog box
      if (resenddefaultIsYes) {
        $('#passcodeYes').addClass('UD_buttonchosen').trigger('focus');
        $('#passcodeNo').removeClass('UD_buttonchosen');
      } else {
        $('#passcodeNo').addClass('UD_buttonchosen').trigger('focus');
        $('#passcodeYes').removeClass('UD_buttonchosen');
      }
      if (resenddefaultIsYes) {
        $('#passcodeYes').addClass('UD_buttonchosen').trigger('focus');
        $('#passcodeNo').removeClass('UD_buttonchosen');
      } else {
        $('#passcodeNo').addClass('UD_buttonchosen').trigger('focus');
        $('#passcodeYes').removeClass('UD_buttonchosen');
      }
    }
{/if}
{if $isEncrypted}
    if (showingPasswordDialog) {
      // Set up the password dialog box
      pwd = $('#n').val();
      $('#decryptPassword').attr('type', 'password');
      $('#decryptPassword').val(pwd);
      $('#decryptPassword').trigger('focus');
      $('#hideDecryptChars').prop('checked', true);
      if (pwd) {
        // Enable OK
        $('#setPasswordButton').prop('disabled', false).removeClass('greyButton');
      } else {
        // Disable OK
        $('#setPasswordButton').prop('disabled', true).addClass('greyButton');
      }
    }
{/if}
  });

{if $isEncrypted && $isClickable}
  // Bind an event to my new facebox cancel event
  $(document).on('cancel.facebox', function() {
    $('#decryptPassword').val('');
    if (showingPasswordDialog)
      cancelDecryptPassword();
    showingPasswordDialog = false;
  });
{/if}

  // Bind to facebox's close event
  // This gets called when the box is closed for any reason
  $(document).on('close.facebox', function(){
{if $isSendable}
    if (showingResendDialog) {
      showingResendDialog   = false;
      showingPasswordDialog = false; // Reset both to be sure
      // Not an if-then-else as I want 3 choices.
      if (resendtheysaidyes) {
        $('#resendWithPasscode').val("yes");
        //$('.content').css('cursor', 'wait');
        return document.resendDropoff.submit();
      }
      if (resendtheysaidno) {
        $('#resendWithPasscode').val("no");
        //$('.content').css('cursor', 'wait');
        return document.resendDropoff.submit();
      }
      return false; // Probably thumped escape, so abandon
    }
{/if}
{if $isEncrypted}
    if (showingPasswordDialog) {
      showingPasswordDialog = false;
      showingResendDialog   = false; // Reset both to be sure
      // We were trying to download a file.
      // So give it another go, *if* we now have a password set.
      // If it's still blank, do nothing.
      if ($('#decryptPassword').val()) {
        if (wantToDownloadAll)
          doDownloadAll();
        else
          downloadEncrypted(-1);
      }
    }
{/if}
  });

  // Get the dialogs and copy them into new objects + remove from the DOM.
  ZendTo.passwordDialog = $('#passwordDialog').html();
  ZendTo.resendDialog   = $('#resendDialog').html();
  $('#passwordDialog').remove();
  $('#resendDialog').remove();

  // Do we want to show and enforce the waiver text?
  // First 2 mean: are we a recipient seeing this?
  {if $isClickable && !$isDeleteable && $showWaiver}
    // Show the waiver
    $('#waiverSpan').css('display', 'block');
    $('#waiverButton').on('click', function() {
      waiverAgreed = true;
      $('#waiverButton').prop('checked', true);
      $('#waiverSpan').css('display', 'none');
      $('#dropoffTable').css('display', 'table');
    });
  {else}
    // Show the main table of the drop-off summary and files
    $('#dropoffTable').css('display', 'table');
  {/if}
  // The "copy to clipboard" button for the link, if it exists
  if ($('#linkbox').length) {
    const copytext = '{t}Click to copy link to clipboard{/t}';
    const copiedtext = '{t}Copied{/t}';
    var linktext = $('#linkbox').val();
    linktext = linktext.match(/^(\S+)/)[0]; // Up to 1st whitespace
    $('#copylinkButton').on('click', function() {
      // Create a temporary input element, fill it, select it, copy it, kill it
      var $temp = $('<input>');
      $("body").append($temp);
      $temp.val(linktext).select();
      document.execCommand("copy");
      $temp.remove();
      // Change the tooltip text. hideComplete() will reset it on mouseleave.
      // I know it doesn't line up right. I can't fix that without major
      // changes to jquery.balloon.js which I didn't write.
      $('#copylinktip').text(copiedtext);
    });
    $('#copylinkButton').balloon({
      position: "top left",
      html: true,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: '<span name="copylinktip" id="copylinktip">'+copytext+'</span>',
      showAnimation: function (d, c) { this.fadeIn(d, c); },
      // This puts the original text back on exit, in case click changed it
      hideComplete: function (d) { $('#copylinktip').text(copytext); }
    });
  }
});

//-->
</script>

<span style="display:none">
{capture assign="resenddefault_t"}{if $defaultEmailPasscode=="true"}{t}Yes{/t}{else}{t}No{/t}{/if}{/capture}
<div id="resendDialog">
  <h1>{t}Include Passcode?{/t}</h1>
  <p>{t}Should the Passcode be included in the emails as well as the Claim ID?{/t}</p><p>{t 1=$resenddefault_t}Click '%1' if you are not sure.{/t}</p>
  <p class="center" style="margin: 25px 20px 20px">
    <a href="javascript:resendYesNo(true);" id="passcodeYes" name="passcodeYes" class="greyButton" style="float:none;">{t}Yes{/t}</a> <a href="javascript:resendYesNo(false);" id="passcodeNo" name="passcodeNo" class="greyButton" style="float:none">{t}No{/t}</a>
  </p>
</div>

<div id="passwordDialog">
        <h1>{t}Decryption Passphrase{/t}</h1>
        <div class="center dark-red"><strong>{t escape=no}You can ask the sender<br/>for the decryption passphrase.{/t}</strong></div>
        <div class="center" style="white-space:nowrap">
        <table border="0" width="100%" style="padding:0px"><tr>
          <td class="ui-widget" style="padding-bottom:5px">
            <label for="decryptPassword" class="UILabel">{t}Passphrase{/t}:</label></td>
          <td class="ui-widget" style="float:left;padding-bottom:5px">
             <input type="text" id="decryptPassword" name="decryptPassword" size="30" autocomplete="off" value=""/></td>
        </tr><tr>
          <td class="ui-widget">&nbsp;</td>
          <td class="ui-widget" style="float:left">
            <input type="checkbox" name="hideDecryptChars" id="hideDecryptChars" checked="checked"/> <label for="hideDecryptChars">{t}Hide characters{/t}</label></td>
          </td>
        </tr><tr>
          <td colspan="2"><button name="setPasswordButton" id="setPasswordButton"  style="margin:0px;margin-top:5px" onclick="javascript:setDecryptPassword();">{t}OK{/t}</button> <button onclick="javascript:cancelDecryptPassword();">{t}Cancel{/t}</button></td>
        </tr></table>
        </div>
</div>
</span>


{if $isSendable || $isDeleteable}
<div style="float:right; display:flex">
{if $isDeleteable}<button id="deleteButton" name="deleteButton" class="UD_textbutton_admin">{t}Delete Dropoff{/t}</button>{else}&nbsp;{/if}
{if $isSendable}<button id="resendButton" name="resendButton" class="UD_textbutton_admin">{t}Resend Dropoff{/t}</button>{else}&nbsp;{/if}
</div>
{/if}

{* If it is not Clickable, Sendable or Deleteable, then it must be the   *}
{* result of a new drop-off. In which case, due to progress bar changes, *}
{* the errors will not have been displayed. So we need to show them      *}
{* again here. *}
{if ! $isClickable && ! $isSendable && ! $isDeleteable && !empty($errors)}
  {if count($errors)>0}
        <div name="error" id="error">
            <table class="UD_error" width="100%">
          {for $i=0;$i<count($errors);$i++}
              <tr>
                <td class="UD_error_title"><i class="fas fa-exclamation-circle fa-fw"></i></td><td class="UD_error_title">{$errors[$i].title|default:"&nbsp;"}</td>
              </tr>
              <tr>
                <td class="UD_error_message"><i class="fas fa-fw"></i></td><td class="UD_error_message">{$errors[$i].text|default:"&nbsp;"}</td>
              </tr>
          {/for}
            </table>
        </div>
  {/if}

{else} {* count($errors) must be <= 0 *}

<h1>{t}Drop-Off Summary{/t}</h1>

<span name="waiverSpan" id="waiverSpan" style="display:none">
  <span class="infoBox waiverBox">
    {t escape=no}This is a terms and conditions waiver that recipients must agree to.
    <br/>To switch it on/off, see the settings <tt>showRecipientsWaiverCheckbox</tt> and <tt>defaultRecipientsWaiver</tt> in <tt>/opt/zendto/config/preferences.php</tt>.
    <br/>It can be long and may contain HTML tags.
    <br/>To change this text:
    <ol>
      <li>look for this text in the <tt>/opt/zendto/config/locale/*_*/LC_MESSAGES/zendto.po</tt> text files</li>
      <li>put your own text in <tt>msgstr&nbsp;"..."</tt> line(s) immediately following it</li>
      <li>run <tt>/opt/zendto/bin/makelanguages</tt> as root</li>
      <li>restart Apache (to ensure it really picks up the new text).</li>
    </ol>
    <p>This is exactly how you change the text for anything in the ZendTo interface. For more info, read <a href="https://zend.to/translators.php">the translations page in the documentation</a>.</p>{/t}
    <label for="waiverButton"><input name="waiverButton" id="waiverButton" type="checkbox"/>
    {t escape=no}I have read, understood and agree to the terms and conditions above.{/t}</label>
  </span>
  <br class="clear"/>
</span>

<table name="dropoffTable" id="dropoffTable" border="0" cellpadding="5" style="width:100%; display:none;">
{if $dropoffFilesCount>0}
  {* First 3 mean it is a new drop-off. *}
  {if ! $isClickable && ! $isSendable && ! $isDeleteable && empty($errors)}
  <tr valign="top">
    <td></td>
    <td colspan="4" style="padding: 12px">
    {if $isEncrypted}
      <span style="font-size: 1.1em">{t}Your files have been encrypted and sent successfully.{/t}<br/>{t 1=$expiresin}They will expire in %1.{/t}</span>
    {else}
      <span style="font-size: 1.1em">{t}Your files have been sent successfully.{/t}<br/>{t 1=$expiresin}They will expire in %1.{/t}</span>
    {/if}
    </td>
  </tr>
  {elseif $isEncrypted && ! $inPickupPHP}
  <tr valign="top">
    <td></td>
    <td colspan="4" style="padding: 12px">
      <span style="font-size: 1.1em">{t}This drop-off is encrypted.{/t}</span>
    </td>
  </tr>
  {/if}
  {if $isClickable}
  <tr valign="top">
    <td></td>
    <td colspan="4" style="padding: 12px">
      <span style="font-size: 1.1em">{t}Click on a filename to download that file.{/t} {t 1=$expiresin}This drop-off will expire in %1.{/t}</span>
    </td>
  </tr>
  {/if}
{/if}
  <tr valign="top">
    <td></td>
    <td>

{if $dropoffFilesCount>0}
      <table id="filesTable" name="filesTable" class="UD_form" cellpadding="4" style="width:100%">
        <thead class="UD_form_header">
          <td colspan="2">{t}Filename{/t}</td>
          <td align="right">{t}Size{/t}</td>
          <td align="center">{t}SHA-256 Checksum{/t}</td>
          <td>{t}Description{/t}</td>
        </thead>
  {foreach from=$files item=f}
        <tr class="UD_form_lined" valign="middle">
      {if $isClickable}
        {if $isEncrypted}
          {* It is encrypted so we need to call some code that POSTs it *}
          <td width="20" align="center"><a href="#_js" onclick="downloadEncrypted({$f.rowID});" title="{$f.mimeType}" alt="{$f.mimeType}"><img src="{$f.icon}" border="0" title="{$f.mimeType}" alt="{$f.mimeType}"/></a></td>
          <td class="UD_form_lined mono"><a href="#_js" onclick="downloadEncrypted({$f.rowID});" name="download_{$f.rowID}" title="{$f.basename}" alt="{$f.basename}">{$f.basename}</a></td>
        {else}
          <td width="20" align="center"><a href="{$downloadURL}{if $auth ne ""}&auth={$auth}{/if}&fid={$f.rowID}" download="{$f.downloadname}"><img src="{$f.icon}" border="0" title="{$f.mimeType}" alt="{$f.mimeType}"/></a></td>
          <td class="UD_form_lined mono"><a href="{$downloadURL}{if $auth ne ""}&auth={$auth}{/if}&fid={$f.rowID}" id="download_{$f.rowID}" name="download_{$f.rowID}" download="{$f.downloadname}" title="{$f.basename}" alt="{$f.basename}">{$f.basename}</a></td>
        {/if}
      {else}
          <td width="20" align="center"><img src="{$f.icon}" border="0" title="{$f.mimeType}" alt="[file]"/></td>
          <td class="UD_form_lined mono">{$f.basename}</td>
      {/if}
          <td class="UD_form_lined" align="right">{$f.length|replace:' ':'&nbsp;'}</td>
          <td class="UD_form_lined" align="center">{if $f.checksum ne ""}<span class="checksum">{$f.checksum|wordwrap:$f.wrapat:"<br/>":true}</span>{else}{t}Not calculated{/t}{/if}</td>
          <td>{$f.description|default:"&nbsp;"}</td>
        </tr>
  {/foreach}
        <tr class="UD_form_footer">
          <td colspan="5" align="center">&nbsp;<br/>{if $dropoffFilesCount ne 1}{t 1=$dropoffFilesCount}%1 files{/t}{else}{t 1=$dropoffFilesCount}%1 file{/t}{/if}<br />
          <div class="center" style="display:none; justify-content:center; align-items:stretch" name="downloadAllDiv" id="downloadAllDiv">
            <button style="width:auto; display:none" id="downloadAllButton" name="downloadAllButton">{t}Download All Files{/t}</button>
  {if $isEncrypted}
            <button style="width:auto" id="downloadZipButton" name="downloadZipButton">{t escape=no}Download All Files<br/>as an Unencrypted Zip{/t}</button>
  {else}
            <button style="width:auto" id="downloadZipButton" name="downloadZipButton">{t escape=no}Download All Files<br/>as a Zip{/t}</button>
            <a name="downloadZipLink" id="downloadZipLink" style="display:none" href="{$downloadURL}{if $auth ne ""}&auth={$auth}{/if}&fid=all">zip</a>
  {/if}
          </div>
          </td>
        </tr>
      </table>
      <form name="resendDropoff" method="post" action="{$zendToURL}{call name=hidePHPExt t='resend.php'}">
{if $isSendable}
        <input type="hidden" name="claimID" value="{$claimID}"/>
        <input type="hidden" name="claimPasscode" value="{$claimPasscode}"/>
        <input type="hidden" name="resendWithPasscode" value="yes"/>
{/if}

  {if $emailAddr ne ""}
        <input type="hidden" name="emailAddr" value="{$emailAddr}"/>
  {/if}
      </form>
      <form name="deleteDropoff" method="post" action="{$zendToURL}{call name=hidePHPExt t='delete.php'}">
{if $isDeleteable}
        <input type="hidden" name="claimID" value="{$claimID}"/>
        <input type="hidden" name="claimPasscode" value="{$claimPasscode}"/>
{/if}

  {if $emailAddr ne ""}
        <input type="hidden" name="emailAddr" value="{$emailAddr}"/>
  {/if}
      </form>

      {* This is the form for the download links, when it is encrypted *}
      <form name="decryptDropoff" method="post" action="{$downloadURL}{*{if $auth ne ""}&auth={$auth}{/if}*}">
{if $isEncrypted}
        <input type="hidden" id="auth" name="auth" value="{$auth}"/>
        <input type="hidden" id="fid" name="fid" value=""/>
        <input type="hidden" id="n" name="n" value=""/>
{/if}
      </form>
{else}
      {t}No files in the dropoff... something is amiss!{/t}
{/if}

    </td>
  </tr>
</table>

{if $dropoffFilesCount>0}
<div class="UILabel">{t}From{/t}:</div> <br class="clear" />
{capture assign="from_t"}<span id="fromName">{$senderName}</span> <span id="fromEmail">&lt;{$senderEmail}&gt;</span> <span id="fromOrg">{$senderOrg}</span>{/capture}
{capture assign="date_t"}{$createdDate|date_format:"%Y-%m-%d&nbsp;%H:%M"}{/capture}
<div id="fromHolder">{t escape=no 1=$from_t 2=$senderHost 3=$date_t}%1 <span>from %2 on %3</span>{/t}</div>

{if $showSubject}
<div class="UILabel">{t}Subject{/t}:</div> <br class="clear" />
<div id="subjectHolder">{$subject}</div>
{/if}

{if $showRecips}
<div class="UILabel">{t}To{/t}:</div> <br class="clear" />
<div id="emailHolder">
  {foreach from=$recipients item=r}
              <div class='emailButton'>{$r.0} &lt;{$r.1}&gt;</div>
  {/foreach}
</div>
{/if}
<br class="clear" />
{/if}

<div class="clearfix" style="display:block; margin-top:3px;">
  <!-- Note and its header on the left -->
  <div style="display:inline-block; float:left; width:50%; vertical-align:top;">
    <div style="display:block;">
    {if $dropoffFilesCount>0}
      <label class="UILabel" style="margin-top:0px; clear:left;" for="comments">{t}Comments{/t}:</label><br />
      <textarea readonly="yes" id="comments" name="comments" style="width: 450px; height: 100px;">{$note}</textarea>
    {else}
      <!-- Padding -->
      <div class="UILabel" style="display:block; float:none; margin-top:0px;">&nbsp;</div>
      &nbsp;<br />
    {/if}
    </div>
  </div>

{if $showIDPasscode}
  <!-- ClaimID and Passcode and their headings on the right -->
  <div style="display:inline-block; float:left; width:50%; vertical-align:top;">
    <div style="display:block;">
      <!-- Padding -->
      <div class="UILabel" style="display:block; float:none; margin-top:0px;">&nbsp;</div>

  {* If it's sendable, they can't be the recipient. *}
  {* The other half is the exception to the elseif below. *}
  {* {if $isSendable || (!$isSendable && $isAuthorizedUser && !$inPickupPHP)} *}
  {if $isSendable || (!$isSendable && $isAuthorizedUser)}
      <div id="sendContainer">
        <table style="table-layout:auto;">
          <tr class="sendContainerLinkText">
          <td style="vertical-align:top; padding-top:4px; width:1%;"><i class="fas fa-info-circle fa-fw"></i></td>
          {capture assign="copybutton"}<button id="copylinkButton" name="copylinkButton" class="resetButton"><i class="fas fa-copy fa-fw"></i></button>{/capture}
          <td colspan="2">{t escape=no 1=$copybutton}To send the files to someone else, send them this link %1, or else the Claim ID & Passcode:{/t}</td>
          </tr><tr>
          <td><i class="fas fa-fw"></i></td>
          <td><textarea readonly="yes" id="linkbox" name="linkbox" style="resize:none; margin-left:0px; word-wrap:normal; white-space:pre; margin-bottom:4px; box-sizing: border-box; width:100%;" wrap="hard" rows="3">{$linkURL}{call name=hidePHPExt t='pickup.php'}?claimID={$claimID}&amp;claimPasscode={$claimPasscode}&nbsp;&nbsp;
{t}Claim ID{/t}: {$claimID}
{t}Claim Passcode{/t}: {$claimPasscode}</textarea></td>
          <td><i class="fas fa-fw" style="width:1%;"></i></td>
          </tr>
        </table>
      </div>
  {elseif $isAuthorizedUser}
      {* They are logged in *}
      <div id="sendContainer">
        <strong>{t}Claim ID{/t}:</strong> {$claimID}<br/>
        <strong>{t}Claim Passcode{/t}:</strong> {$claimPasscode}
      </div>
  {else}
      &nbsp;
  {/if}
    </div>
  </div>
{/if}
</div>
<br />

<table border="0" cellpadding="5">

<!-- Show all the recipients and their pick-up details -->
{if $showRecips}
  <tr>
    <td colspan="2">
  {if $pickupsCount>0}
      <table width="100%" class="UD_form" cellpadding="4">
        <thead class="UD_form_header">
          <td>{t}Picked-up on date...{/t}</td>
          <td>{t}...from remote address...{/t}</td>
          <td>{t}...by recipient.{/t}</td>
        </thead>
    {foreach from=$pickups item=p}
        <tr class="UD_form_lined" valign="middle">
          <td class="UD_form_lined mono">{$p.pickupDate|date_format:"%Y-%m-%d&nbsp;%H:%M"}</td>
          <td class="UD_form_lined">{$p.hostname|default:"&nbsp;"}</td>
          <td>{$p.pickedUpBy|default:"&nbsp;"}</td>
        </tr>
    {/foreach}
        <tr class="UD_form_footer">
          <td colspan="3" align="center">{if $pickupsCount ne 1}{t 1=$pickupsCount}%1 pickups{/t}{else}{t}1 pickup{/t}{/if}</td>
        </tr>
      </table>
  {else}
    {t}None of the files has been picked-up yet.{/t}
  {/if}
    </td>
  </tr>
{/if}
</table>

{/if} {* End of "don't show anything if no files present" *}

</div>

{include file="footer.tpl"}
