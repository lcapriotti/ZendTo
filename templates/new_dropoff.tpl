{$thisTemplate=$smarty.template}{include file="header.tpl"}
<script type="text/javascript" src="js/jquery-ui-1.12.1.min.js"></script>
<script type="text/javascript" src="js/dropoff.js"></script>
<script type="text/javascript" src="js/jquery.balloon.min.js"></script>
<script type="text/javascript" src="js/addressbook.js"></script>
<!-- FormData is from https://github.com/jimmywarting/FormData -->
<script type="text/javascript" src="js/formdata.min.js"></script>
<script type="text/javascript">
  {include file="new_dropoff.js.tpl"}
</script>

  <div style="padding:4px;border:2px solid #C01010;background:#FFF0F0;color:#C01010;text-align:justify;" class="round">
    <strong>{t}PLEASE NOTE{/t}</strong>
    <br>
    <br>
    {t escape=no 1=#ServiceTitle# 2=#FavouriteWindowsZip#}Files uploaded to %1 are scanned for viruses.  But still exercise the same degree of caution as you would with any other file you download.{/t}{if $enforceEncrypt=="false"} {t escape=no 1=#ServiceTitle# 2=#FavouriteWindowsZip#}Users are also <strong>strongly encouraged</strong> to encrypt every file if any contain sensitive information (e.g. personal private information)!{/t}{/if}
  </div>

{if $isAuthorizedUser}
  <p>{t 1=#OrganizationShortName#}Use this form to drop-off (upload) one or more files for anyone (either a %1 user or others). The recipient will receive an automated email containing the information you enter below and instructions for downloading the file. Your IP address will also be logged and sent to the recipient for identity confirmation purposes.{/t}</p>
{else}
  <p>{t 1=#OrganizationShortName#}Use this form to drop-off (upload) one or more files for a %1 user. The recipient will receive an automated email containing the information you enter below and instructions for downloading the file. Your IP address will also be logged and sent to the recipient for identity confirmation purposes.{/t}</p>
{/if}

<form name="dropoff" id="dropoff" method="post" enctype="multipart/form-data">

<input type="hidden" name="Action" value="dropoff"/>
<input type="hidden" id="auth" name="auth" value="{$authKey}"/>
<input type="hidden" id="req" name="req" value="{$reqKey}"/>
<input type="hidden" id="senderOrganization" name="senderOrganization" value="{$senderOrg}"/>
<input type="hidden" id="encryptPassword" name="encryptPassword" value=""/>
<input type="hidden" id="chunkName" name="chunkName" value="{$chunkName}"/>

<div id="newdropoff-boxes">
  <!-- First boxes about the sender and subject -->
  <span id="fromLabel" class="labels">{t}From{/t}:</span>
  <span id="subjectLabel" class="labels">{t}Subject{/t}:</span>
  <span id="fromHolder" class="text"><span id="fromName">{$senderName}</span> <span id="fromEmail">&lt;{$senderEmail}&gt;</span> <span id="fromOrg">{$senderOrg}</span></span>
  <input type="text" id="subject" name="subject" class="text" value="{$subject}" {if !$isAuthorizedUser}readonly {/if}/>
  <!-- Then the recipients -->
  <span id="emailLabel" class="labels">{t}To{/t}:</span>
  <span id="emailHolder" class="text"> <a id="addRecipients" href="#"><img src="images/swish/plus-circle-frame.png" alt="Add recipients" /></a> </span>
  <!-- Then the note on the left side -->
  <span id="noteLabel" class="labels">{t}Short note to the Recipients{/t}:</span>
  <span id="noteHolder"><textarea name="note" id="note" wrap="soft">{$note}</textarea>
    <span id="noteLengthText"></span>
  </span>

  <!-- And all the checkboxes on the right side -->
  <!-- Hidden until we have removed everything we don't want to show -->
  <div id="newdropoff-checkboxes" name="newdropoff-checkboxes" style="visibility:hidden">
    <input type="checkbox" name="encryptFiles" id="encryptFiles" class="newdropoff-encryptSpan" />
    <label name="encryptFilesLabel" id="encryptFilesLabel" for="encryptFiles" class="ndcbLabel newdropoff-encryptSpan">{t}Encrypt every file{/t}{if !$allowPassphraseDialog} <i id='encrypt-balloon' name='encrypt-balloon' class='fas fa-info-circle' style="text-indent: 0px"></i>{/if}</label>
    <input type="checkbox" name="checksumFiles" id="checksumFiles" class="newdropoff-checksumSpan" checked="checked" />
    <label name="checksumFilesLabel" id="checksumFilesLabel" for="checksumFiles" class="ndcbLabel newdropoff-checksumSpan">{t}Calculate SHA-256 checksum of each file{/t}</label>
    <input type="checkbox" name="confirmDelivery" id="confirmDelivery" class="newdropoff-confirmSpan" checked="checked" />
    <label for="confirmDelivery" class="ndcbLabel newdropoff-confirmSpan">{t}Send me an email when each recipient picks up the files{/t}</label>
    <input type="checkbox" name="informRecipients" id="informRecipients" class="newdropoff-informSpan newdropoff-informRecipientsSpan" checked="checked" />
    <label id="informRecipientsLabel" name="informRecipientsLabel" for="informRecipients" class="ndcbLabel newdropoff-informSpan newdropoff-informRecipientsSpan">{t}Send email message to recipients{/t}</label>
    <input type="checkbox" name="informPasscode" id="informPasscode" class="newdropoff-informPasscodeSpan newdropoff-informSpan" checked="checked" />
    <label id="informPasscodeLabel" name="informPasscodeLabel" for="informPasscode" class="ndcbLabel newdropoff-informPasscodeSpan newdropoff-informSpan">{t}which includes Passcode as well as Claim ID{/t}</label>
    <input type="checkbox" name="recipWaiver" id="recipWaiver" class="newdropoff-waiverSpan" checked="checked" />
    <label id="recipWaiverLabel" name="recipWaiverLabel" for="recipWaiver" class="ndcbLabel newdropoff-waiverSpan">{t}Recipients must agree to terms and conditions{/t}</label>
    <input type="number" name="lifedays" id="lifedays" class="newdropoff-lifetimeSpan" min="0" max="{$keepForDays}" value="{$defaultLifetime}" />
    <label id="lifetimeLabel" name="lifetimeLabel" for="lifedays" class="ndcbLabel newdropoff-lifetimeSpan">{t}days until drop-off expires{/t}</label>
  </div>
</div>

{* Old pre-grid design
<div class="UILabel">{t}From{/t}:</div> <br class="clear" />
<div id="fromHolder"><span id="fromName">{$senderName}</span> <span id="fromEmail">&lt;{$senderEmail}&gt;</span> <span id="fromOrg">{$senderOrg}</span></div>
<br class="clear" />
<div class="UILabel">{t}To{/t}:</div> <br class="clear" />
<div id="emailHolder"> <a id="addRecipients" href="#"><img src="images/swish/plus-circle-frame.png" alt="Add recipients" /></a> </div>
<br class="clear" />
<!-- Note and tick-boxes -->
<div class="clearfix" style="display:block; margin-top:3px;">
  <div class="UILabel" style="width:auto; margin-top:0px;">{t}Short note to the Recipients{/t}:</div><br class="clear" />
  <!-- Note on the left and tick-boxes on the right -->
  <div style="display:inline-block; width:49%; float:left;">
    <div style="display:block; float:left; vertical-align:top;">
      <textarea name="note" id="note" wrap="soft" style="width:450px;height:90px">{$note}</textarea><br class="clear" />
      <span id="noteLengthText" style="float:right"></span>
    </div>
  </div>

 ** Old checkboxes **
  <div id="newdropoff-checkboxes" name="newdropoff-checkboxes" style="visibility:hidden">
    <span name="encryptSpan" id="encryptSpan"><label name="encryptFilesLabel" id="encryptFilesLabel" for="encryptFiles" class="ndcbLabel"><input type="checkbox" name="encryptFiles" id="encryptFiles"/> {t}Encrypt every file{/t}{if !$allowPassphraseDialog} <i id='encrypt-balloon' name='encrypt-balloon' class='fas fa-info-circle' style="text-indent: 0px"></i>{/if}</label></span>
    <span name="checksumSpan" id="checksumSpan"><label name="checksumFilesLabel" id="checksumFilesLabel" for="checksumFiles" class="ndcbLabel"><input type="checkbox" name="checksumFiles" id="checksumFiles" checked="checked"/> {t}Calculate SHA-256 checksum of each file{/t}</label></span>
    <span name="confirmSpan" id="confirmSpan"><label for="confirmDelivery" class="ndcbLabel"><input type="checkbox" name="confirmDelivery" id="confirmDelivery" checked="checked"/> {t}Send me an email when each recipient picks up the files{/t}</label></span>
    <span name="informSpan" id="informSpan"><label id="informRecipientsLabel" name="informRecipientsLabel" for="informRecipients" class="ndcbLabel"><input type="checkbox" name="informRecipients" id="informRecipients" checked="checked" /> {t}Send email message to recipients{/t}</label><span name="passcodeSpan" id="passcodeSpan">
    <label id="informPasscodeLabel" name="informPasscodeLabel" for="informPasscode" class="ndcbLabel"><input type="checkbox" name="informPasscode" id="informPasscode" checked="checked" /> {t}which includes Passcode as well as Claim ID{/t}</label></span></span>
    <span name="waiverSpan" id="waiverSpan"><label id="recipWaiverLabel" name="recipWaiverLabel" for="recipWaiver" class="ndcbLabel"><input type="checkbox" name="recipWaiver" id="recipWaiver" checked="checked"/> {t}Recipients must agree to terms and conditions{/t}</label></span>
    <span name="lifetimeSpan" id="lifetimeSpan"><label id="lifetimeLabel" name="lifetimeLabel" for="lifetime" class="ndcbLabel"><input type="number" name="lifetime" id="lifetime" min="0" max="40" value="40" placeholder="Days"/> {t}days until drop-off expires{/t}</label></span>
  </div>
</div>
End of Old checkboxes and old pre-grid design *}

<span style="display:none">
<!-- Add Recipients dialog (hidden till we need it) -->
<!-- This div is removed from the DOM in $(document).ready() -->
<div id="addNewRecipient">
  <h1>{t}Add Recipients{/t}</h1>
  <div class="center buttonHolder" style="display:flex; justify-content:center;"><button id="showSingleDialog">{t}Add One{/t}</button> <button id="showMultipleDialog" class="greyButton">{t}Add Many{/t}</button></div>
        
  <!-- Sending to a single recipient -->
  <div id="sendSingle" class="center">
    <!-- Centre the table itself -->
    <table class="ui-widget" style="margin:0px auto 3px auto;">
      <tr>
        <td style="text-align:right;"><label for="recipName" class="UILabel" style="float:right; margin-right: 3px;">{t}Name{/t}:</label></td>
        <td style="text-align:left;"><input type="text" id="recipName" name="recipName" size="33" placeholder="{t}Adds to your address book{/t}" autocomplete="off" value="{$recipName_1}"/></td>
      </tr>
      <tr>
        <td style="text-align:right;"><label for="recipEmail" class="UILabel" style="float:right; margin-right:3px;">{t}Email{/t}:</label></td>
        <td style="text-align:left;"><input type="text" id="recipEmail" name="recipEmail" size="33" autocomplete="off" value="{$recipEmail_1}"/></td>
      </tr>
    </table>
    <div class="center buttonHolder" style="display:flex; justify-content:center;"><button onclick="javascript:addSingleRecipient();">{t}Add{/t}</button> <button onclick="javascript:add1RecipientAndClose();">{t}Add & Close{/t}</button></div>
  </div>

  <div id="sendMultiple" class="center ui-widget">
    <textarea id="multipleRecipients" rows="10" cols="38" placeholder="{t}Bulk add recipients{/t}"></textarea>
    <p>{t}One recipient per line, for example:{/t} <br /><em>{t}Recipient's Name email@example.com{/t}</em></p>
    <div class="center"><button id="addMultipleRecipients">{t}Verify{/t}</button></div>
  </div>
</div>

<!-- Encryption Passphrase dialog (hidden till we need it) -->
<div id="encryptPasswordDialog">
  <h1>{t}Encryption Passphrase{/t}</h1>
  <div class="center dark-red"><strong>{t escape=no}This passphrase will not be sent to the recipients.<br/>You need to do this yourself.{/t}</strong></div>
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


<!-- Add Files button -->
<div class="center" style="margin-top:20px; display:block;">
  <input type="file" id="AddFiles" multiple style="display:none" onchange="addFiles(this.files)">
  <button id="AddFilesButton">{t}Add Files{/t}</button><span id="libraryselector"> <select id="file_select" name="file_select" class="file_select"><option value="-1">{t}and/or select a file...{/t}</option></select></span>
</div>

<table id="uploadTable" width="100%" border="0" cellpadding="5">
  <tr valign="top">
   <td> </td>
   <td>
    <table class="UD_form" cellpadding="4">
      <thead class="UD_form_header"><tr>
        <td> </td>
        <td>{t}Filename{/t}</td>
        <td class="ndfilesize">{t}Size{/t}</td>
        <td colspan="2">{t}Description{/t}</td>
      </tr></thead>
      <tbody id="uploadFiles">
      </tbody>
    </table>
   </td>
  </tr>
</table>

</form>


<div id="uploadDialog" style="display: none;">
  <h1>{t}Uploading...{/t}</h1>
  <div id="progressBarContainer" style="border: none; height: 80px; width: 350px; margin-left: 25px;">
    <div id="progressContainer">
      <div id="progressouter" style="display: block; margin-bottom: 10px;">
        <div id="progressinner" style="width:0%;"></div>
      </div>
      <div id="percentText" style="visibility:visible;"></div>
    </div>
  </div>
</div>

<div class="center" id="DropoffButton"><span id="totalSize"></span><br /><button id="RealDropoffButton" onclick="submitform();">{t}Drop-off Files{/t}</button></div>

<div id="overlay" style="display:none">
  <span id="overlaytext">{t escape=no}Drop to<br/>Add Files{/t}<br/><span id="overlaytextsub">{t}(It will copy, not move){/t}</span></span>
</div>

{include file="footer.tpl"}
