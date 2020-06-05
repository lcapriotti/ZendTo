{$thisTemplate=$smarty.template}{include file="header.tpl"}
<link rel="stylesheet" href="css/jquery-ui.min.css">
<script type="text/javascript" src="js/jquery-ui-1.12.1.min.js"></script>
<script type="text/javascript" src="js/jquery.balloon.min.js"></script>
<script type="text/javascript" src="js/addressbook.js"></script>
<script type="text/javascript">
<!--
// These 2 have to be declared here so Smarty can substitute values
var addressbook = {$addressbook};
var deleteText = '{t}Delete{/t}';

// 2 allowable formats for email addresses. Not used for
// checking, just for UI optimisation.
var emailFormatRegex = /(\S+@\S+)/;
var emailBracketRegex = /<(\S+@\S+)>/;

var maxNoteLength = {$maxNoteLength};
var noteLength = 0;
var minPassphraseLength = '{$minPassphraseLength}';
var showingPasswordDialog = false;
var tryingToUpload = false;
var ZendTo = new Object();

function updateNoteLength() {
        noteLength = $('#note').val().length;
        var left = maxNoteLength - noteLength;
        if (left < 0) {
          $('#noteLengthText').text('{t}__CHARS__ too long{/t}'.replace('__CHARS__', (0-left)));
          $('#noteLengthText').addClass("notetoolong");
        } else {
          $('#noteLengthText').text('{t}__CHARSLEFT__ / __MAXLENGTH__ left{/t}'.replace('__CHARSLEFT__', left).replace('__MAXLENGTH__', maxNoteLength));
          $('#noteLengthText').removeClass("notetoolong");
        }
}

// Handler for ticking / unticking "Send email" checkbox
function sendEmail() {
  if (this.checked) {
    $('#sendRequestButton').text('{t}Send the Request{/t}');
  } else {
    $('#sendRequestButton').text('{t}Show the Link{/t}');
  }
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

function validateForm()
{
  //if ( document.req.recipName.value == "" ) {
  //  alert("{t}Please enter the recipient's name first!{/t}");
  //  $('#recipName').focus();
  //  return false;
  //}
  if ( document.req.recipEmail.value == "" ) {
    alert("{t}Please enter the recipient's email address first!{/t}");
    $('#recipEmail').focus();
    return false;
  }
  // Are they sending an email without a subject?
  if ( $('#sendEmail').prop('checked')  && $('#subject').val().length == 0 ) {
    alert("{t}Please enter the email subject first!{/t}");
    $('#subject').focus();
    return false;
  }
  if ( noteLength > maxNoteLength ) {
    alert("{t}Your note is too long!{/t}");
    return false;
  }
  if (blankPassword() || checkPassword() !== '') {
    // But only if they wanted to encrypt
    if ($('#encryptFiles').prop('checked')) {
      // Show the password entry dialog
      $('#encryptFiles').prop('checked', false).trigger('change');
      $('#encryptFiles').prop('checked', true).trigger('change');
      tryingToUpload = true;
      return false;
    }
  }
  return true;
}

$(document).ready(function() {
  // Setup the address book autocompletion
  addAddressBook();

  // If they press return on the recip fields, move focus
  $('#recipName').on("keyup", function(e) {
    if (e.keyCode == 13) $('#subject').focus();
  });
  $('#recipEmail').on("keyup", function(e) {
    if (e.keyCode == 13) $('#subject').focus();
  });

  // If they typed an email address into the name field,
  // move it to the email field automatically.
  $('#recipName').on('change', function(e) {
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

  // Setup the counter & initial value for the note length
  $('#note').on("keyup", updateNoteLength);
  updateNoteLength();

  $(document).on("keyup", '#encryptPassword1', keyEncryptPassword);
  $(document).on("keyup", '#encryptPassword2', keyEncryptPassword);
  $(document).on('change', '#hideEncryptChars', hideEncryptChars);
  $(document).on('change', '#sendEmail', sendEmail);

  $('#encryptFiles').on('change', function() {
    if (this.checked) {
      // Box just ticked
      showingPasswordDialog = true;
      $.facebox(ZendTo.encryptPasswordDialog);
    } else {
      encryptFiles = false;
    }
  });

  // Bind to the reveal of facebox
  $(document).on('reveal.facebox', function(){
    // Set up the password dialog box
    pwd = $('#encryptPassword').val();
    $('#encryptPassword1').attr('type', 'password')
                          .val(pwd)
                          .focus();
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
    if (showingPasswordDialog) {
      // The were entering a password
      if (blankPassword() || checkPassword() !== '') {
        // We haven't got a good non-blank password,
        // so re-present the password dialog.
        //$('#encryptFiles').prop('checked', true).trigger('change');
        1; // no-op?
      } else {
        // We have now got a good password, so if they are trying
        // to hit the Upload button, then try it again as it should
        // succeed this time. This will recurse, and we aren't
        // clearing up, but at the end of the upload all of this
        // gets thrown away anyway.
        if (tryingToUpload) $('#req').submit();
      }
    } else {
      // Focus on the 'note' element
      $('#note').focus();
      showingPasswordDialog = false;
    }
  });

  // Get the password form and copy it into a new object + remove it
  ZendTo.encryptPasswordDialog = $('#encryptPasswordDialog').html();
  $('#encryptPasswordDialog').remove();

  $('#encrypt-balloon').balloon({
      position: "top right",
      html: true,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: '{t escape=no}Optional: if you select this and set a passphrase, the drop-off will be encrypted. The person sending the files will never know the passphrase.{/t}',
      showAnimation: function (d, c) { this.fadeIn(d, c); }
  });
  $('#sendEmail-balloon').balloon({
      position: "top right",
      html: true,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: '{t escape=no}This is normally selected. If you deselect it, the link and instructions will not be sent by email. Instead you will just be shown the link they need, so you can send it by other means.{/t}',
      showAnimation: function (d, c) { this.fadeIn(d, c); }
  });

});

//
// Encryption dialog support
//

// Click cancel in the password dialog ==> untick encryption
function cancelEncryptPassword() {
  tryingToUpload = false; // Abandon any ongoing upload attempt
  $('#encryptFiles').prop('checked', false);
  $(document).trigger('close.facebox');
}

// Click OK/Set in password dialog ==> save password
function setEncryptPassword() {
  if (!blankPassword() && checkPassword() === '') {
    $('#encryptPassword').val(one);
    $(document).trigger('close.facebox');
  } else {
    $('#setPasswordButton').prop('disabled', true).addClass('greyButton');
    $('#encryptFiles').prop('checked', false);
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

//-->
</script>

    <!-- Left-hand side with all the drop-off information -->
    <form name="req" id="req" method="post"
     action="{$zendToURL}{call name=hidePHPExt t='req.php'}" enctype="multipart/form-data"
     onsubmit="return validateForm();">

<h1>{t}Request a Drop-off{/t}</h1>

<p>
{t}This web page will allow you to send a request to one of more other people requesting that they send (upload) one or more files for you.  The recipient will receive an automated email containing the information you enter below and instructions for uploading the file(s).{/t}
</p>
<p>
{t 1=$requestTTL}The request created will be valid for %1.{/t}
</p>

<div class="UILabel">{t}From{/t}:</div> <br class="clear" />
<div id="fromHolder"><span id="fromName">{$senderName}</span> <span id="fromEmail">&lt;{$senderEmail}&gt;</span> <span id="fromOrg"><label for="senderOrg">{t}Organization{/t}:</label> <input type="text" id="senderOrg" name="senderOrg" class="toosmall" size="30" {if !$senderOrgEditable}readonly="true"{/if} value="{$senderOrg}"/></span></div>

<br class="clear" />
<div class="UILabel">{t}To{/t}:</div> <br class="clear" />
<div id="emailHolder" class="ui-widget"> <label id="recipNameLabel" name="recipNameLabel" for="recipName">{t}Name{/t}:</label> <input type="text" id="recipName" name="recipName" class="toosmall" size="33" placeholder="{t}Adds to your address book{/t}" autocomplete="off" value=""/> <label id="recipEmailLabel" name="recipEmailLabel" for="recipEmail">{t}Email(s){/t}:</label> <input type="text" id="recipEmail" name="recipEmail" class="toosmall" size="33" autocomplete="off" value=""/></div>

<div class="UILabel"><label for="subject">{t}Subject{/t}:</label></div> <br class="clear" />
<input type="text" id="subject" name="subject" class="toosmall" size="60" value=""/>

<br class="clear" />
<div class="UILabel"><input type="checkbox" name="encryptFiles" id="encryptFiles" {if $defaultEncryptRequests}checked="checked" {/if}/> <label name="encryptFilesLabel" id="encryptFilesLabel" for="encryptFiles">{t}Encrypt every file{/t}</label> <i id='encrypt-balloon' name='encrypt-balloon' class='fas fa-info-circle' style='vertical-align:right'></i></div>
<br class="clear" />
<div class="UILabel"><input type="checkbox" name="sendEmail" id="sendEmail" checked="checked" /> <label name="sendEmailLabel" id="sendEmailLabel" for="sendEmail">{t}Send email{/t}</label> <i id='sendEmail-balloon' name='sendEmail-balloon' class='fas fa-info-circle' style='vertical-align:right'></i></div>

<br class="clear" /><br class="clear" />

<label for="note">{t}Note{/t}:</label> {t}This will be sent to the recipient. It will also be included in the resulting drop-off sent to you.{/t}<br/>
<table>
<tr><td><textarea name="note" id="note" wrap="soft" style="width:450px; height:70px"></textarea></td></tr>
<tr><td><span id="noteLengthText" style="float:right"></span></td></tr>
</table>


<table border="0"><tr valign="top">
  <td>

      <input type="hidden" name="Action" value="send"/>
      <input type="hidden" id="encryptPassword" name="encryptPassword" value=""/>
      <table border="0" cellpadding="4">

        <tr class="footer">
          <td width="100%" align="center">
            <button id="sendRequestButton" type="submit" style="width:inherit">{t}Send the Request{/t}</button>
          </td>
        </tr>
      </table>
  </td>
</tr></table>

<!-- Hidden dialogs -->
<span style="display:none">
<!-- Encryption Passphrase dialog (hidden till we need it) -->
<div id="encryptPasswordDialog">
  <h1>{t}Encryption Passphrase{/t}</h1>
  <div class="center dark-red"><strong>{t escape=no}Do not lose or forget this passphrase!{/t}</strong></div>
  <div class="center" style="white-space:nowrap">
  <table border="0" width="100%" style="padding:0px"><tr>
    <td class="ui-widget" style="padding-bottom:5px">
      <label for="encryptPassword1" class="UILabel">{t}Passphrase{/t}:</label></td>
    <td class="ui-widget" style="float:left;padding-bottom:5px">
            <input type="password" id="encryptPassword1" name="encryptPassword1" size="30" autocomplete="off" value=""/></td>
  </tr><tr>
    <td class="ui-widget" style="padding-bottom:5px">
      <label for="encryptPassword2" class="UILabel">{t}And again{/t}:</label></td>
    <td class="ui-widget" style="float:left;padding-bottom:5px">
            <input type="password" id="encryptPassword2" name="encryptPassword2" size="30" autocomplete="off" value=""/></td>
  </tr><tr>
    <td class="ui-widget">&nbsp;</td>
    <td class="ui-widget" style="float:left">
      <input type="checkbox" name="hideEncryptChars" id="hideEncryptChars" checked="checked"/> <label for="hideEncryptChars">{t}Hide characters{/t}</label></td>
  </tr><tr>
    <td class="ui-widget">&nbsp;</td>
    <td class="ui-widget" style="float:left">
      <span class="password-error" name="passwordError" id="passwordError">&nbsp;</span>
    </td>
  </tr><tr>
    <td colspan="2"><button name="setPasswordButton" id="setPasswordButton" disabled="disabled" class="greyButton" style="margin:0px;margin-top:5px" onclick="javascript:setEncryptPassword();">{t}OK{/t}</button>{if $enforceEncrypt=="false"} <button onclick="javascript:cancelEncryptPassword();">{t}Cancel{/t}</button>{/if}</td>
  </tr></table>
  </div>
</div>
</span> <!-- End of hidden dialogs -->

</form>


<span style="display:none">

{include file="footer.tpl"}
