{$thisTemplate=$smarty.template}{include file="header.tpl"}
<link rel="stylesheet" href="css/jquery-ui.min.css">
<link rel="stylesheet" href="css/jquery.datetimepicker.min.css">
<script type="text/javascript" src="js/jquery-ui-1.12.1.min.js"></script>
<script type="text/javascript" src="js/jquery.balloon.min.js"></script>
<script type="text/javascript" src="js/php-date-formatter.min.js"></script>
<script type="text/javascript" src="js/jquery.datetimepicker.full.min.js"></script>
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
  // Validate date/times and return them as seconds since epoch
  var startdt, expirydt;
  startdt = $('#start').datetimepicker('getValue').getTime();
  $('#startTime').val(Math.floor(startdt / 1000));
  expirydt = $('#expiry').datetimepicker('getValue').getTime();
  $('#expiryTime').val(Math.ceil(expirydt / 1000));
  if (expirydt <= startdt) {
    alert("{t}Your request must expire after it starts!{/t}");
    return false;
  }
  // Everything was okay.
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
      contents: "{t escape=no}Optional: if you select this and set a passphrase, the drop-off will be encrypted. The person sending the files will never know the passphrase.{/t}",
      showAnimation: function (d, c) { this.fadeIn(d, c); }
  });
  $('#sendEmail-balloon').balloon({
      position: "top right",
      html: true,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: "{t escape=no}This is normally selected. If you deselect it, the link and instructions will not be sent by email. Instead you will just be shown the link they need, so you can send it by other means.{/t}",
      showAnimation: function (d, c) { this.fadeIn(d, c); }
  });
  $('#recipEmail').balloon({
      position: "top center",
      html: true,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: "{t escape=no}Multiple email addresses should be separated with a comma \",\" or semicolon \";\". Each recipient will be sent a different link.{/t}",
      showAnimation: function (d, c) { this.fadeIn(d, c); }
  });

  // Datetimepicker setup
  var dtlocale = '{$currentLocale}';
  switch(dtlocale) {
    case 'en_GB':
    case 'pt_BR':
    case 'sr_YU':
    case 'zh_TW':
      // These 4 are special cases, just swap _ to -
      dtlocale = dtlocale.replace('_', '-');
      break;
    default:
      // Rest just want the chars before the _
      var under = dtlocale.indexOf('_');
      dtlocale = dtlocale.substring(0, under!=-1 ? under : dtlocale.length);
  }
  $.datetimepicker.setLocale(dtlocale);
  // Now!
  var now = new Date();
  var plus1week = new Date();
  plus1week.setTime(now.getTime() + {$requestTTLms});
  jQuery('#start').datetimepicker({
    format: 'Y-m-d H:i',
    minDate: 0, // Disallow dates in the past
    maxDate: '+1971/01/01', // 1 year in the future
    value: now, // What's shown in the text field at start
    startDate: now, // What's initially highlighted in picker
    defaultDate: now, // and defaultDate ??
    defaultTime: now, // Defaults to now ??
    step: 30, // Time picker in 30 minute steps
    mask: true, // Auto generate input mask
    todayButton: true, // Show Today button
    yearStart: now.getFullYear(), // fast year selector starts here
    yearEnd: now.getFullYear()+1, // fast year selector ends here
    roundTime: 'floor', // round times down
  });
  var plus1week = new Date();
  plus1week.setTime(now.getTime() + {$requestTTLms});
  jQuery('#expiry').datetimepicker({
    format: 'Y-m-d H:i',
    minDate: 0, // Disallow dates in the past
    maxDate: '+1971/02/01', // 1 year + 1 month in the future
    value: plus1week, // What's shown in the text field at start
    startDate: plus1week, // What's initially highlighted in picker
    defaultDate: plus1week, // and defaultDate ??
    defaultTime: plus1week, // Defaults to now ??
    step: 30, // Time picker in 30 minute steps
    mask: true, // Auto generate input mask
    todayButton: true, // Show Today button
    yearStart: now.getFullYear(), // fast year selector starts here
    yearEnd: now.getFullYear()+1, // fast year selector ends here
  });

}); // end of document.ready()

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
{t}Use this form to send a request to one of more other people requesting that they send (upload) one or more files for you.  The recipient will receive an automated email containing the information you enter below and instructions for uploading the file(s).{/t}
</p>
<p>
{t 1=$requestTTL}Unless you change the dates or times below, the request created will be valid for %1.{/t}
</p>

<div id="request-boxes">
  <!-- First boxes about the sender and org -->
  <span id="fromLabel" class="labels">{t}From{/t}:</span>
  <span id="orgLabel" class="labels">{t}Organization{/t}:</span>
  <span id="fromHolder" class="text"><span id="fromName">{$senderName}</span> <span id="fromEmail">&lt;{$senderEmail}&gt;</span></span>
  <input type="text" id="senderOrg" name="senderOrg" class="text" {if !$senderOrgEditable}readonly="true"{/if} value="{$senderOrg}"/>

  <!-- Then the recipients -->
  <span id="recipNameLabel" class="labels">{t}To{/t}:</span>
  <span id="recipEmailLabel" class="labels">{t}Email(s){/t}:</span>
  <input type="text" id="recipName" name="recipName" class="text" placeholder="{t}Name: adds to your address book{/t}" autocomplete="off" value=""/>
  <input type="text" id="recipEmail" name="recipEmail" class="text" placeholder="{t}One or more email addresses{/t}" autocomplete="off" value=""/>

  <!-- Then the subject -->
  <span id="subjectLabel" class="labels">{t}Subject{/t}:</span>
  <input type="text" id="subject" name="subject" class="text" placeholder="{t}Subject line of the email{/t}" autocomplete="off" value=""/>

  <!-- Then the date/time limits -->
  <span id="timelimit">
    <span id="startLabel" class="labels">{t}Drop-off must occur between{/t}:</span>
    <input type="text" id="start" name="start" class="text" size="15" autocomplete="off"/>
    <span id="expiryLabel" class="labels">{t}and{/t}</span>
    <input type="text" id="expiry" name="expiry" class="text" size="15" autocomplete="off" />
  </span>

  <!-- Then the note -->
  <span id="noteLabel" class="labels">{t}Note{/t}: {t}This will be sent to the recipient. It will also be included in the resulting drop-off sent to you.{/t}</span>
  <span id="noteHolder"><textarea name="note" id="note" wrap="soft"></textarea>
    <span id="noteLengthText">&nbsp;</span>
  </span>

  <!-- And all the checkboxes on the right side -->
  <input type="checkbox" name="encryptFiles" id="encryptFiles" {if $defaultEncryptRequests}checked="checked" {/if} class="request-encryptSpan request-checkbox" />
  <label name="encryptFilesLabel" id="encryptFilesLabel" for="encryptFiles" class="ndcbLabel request-encryptSpan request-checklabel">{t}Encrypt every file{/t} <i id='encrypt-balloon' name='encrypt-balloon' class='fas fa-info-circle' style='vertical-align:right'></i></label>
  <label class="ndcbLabel request-space request-checklabel">&nbsp;</label>
  <input type="checkbox" name="sendEmail" id="sendEmail" class="request-sendEmailSpan request-checkbox" checked="checked" />
  <label name="sendEmailLabel" id="sendEmailLabel" for="sendEmail" class="ndcbLabel request-sendEmailSpan request-checklabel">{t}Send email{/t} <i id='sendEmail-balloon' name='sendEmail-balloon' class='fas fa-info-circle' style='vertical-align:right'></i></label>

</div> <!-- end of request-boxes -->

<div class="center"><button id="sendRequestButton" type="submit" style="width:inherit">{t}Send the Request{/t}</button></div>

<input type="hidden" name="Action" value="send"/>
<input type="hidden" id="encryptPassword" name="encryptPassword" value=""/>
<input type="hidden" id="startTime" name="startTime" value="0"/>
<input type="hidden" id="expiryTime" name="expiryTime" value="0"/>

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

{include file="footer.tpl"}
