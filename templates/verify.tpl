{$thisTemplate=$smarty.template}{include file="header.tpl"}

<script type="text/javascript">
<!--

var $fullFormTextVisible = true;

function validateForm()
{
{if $allowUploads}
  if ( ! $fullFormTextVisible ) {
    // Only request code visible
    if ( document.dropoff.req.value == "" ) {
      alert("{t}Please enter your Request Code before submitting.{/t}");
      //JQ document.dropoff.req.focus();
      $('#req').trigger('focus');
      return false;
    }
  } else {
    // Full form visible
    if ( document.dropoff.senderName.value == "" ) {
      alert("{t}Please enter your name before submitting.{/t}");
      //JQ document.dropoff.senderName.focus();
      $('#senderName').trigger('focus');
      return false;
    }
    if ( document.dropoff.senderOrganization.value == "" ) {
      document.dropoff.senderOrganization.value = "-";
      // alert("Please enter your organization before submitting.");
      //JQ // document.dropoff.senderOrganization.focus();
      // $('#senderOrganization').trigger('focus');
      // return false;
    }
    if ( document.dropoff.senderEmail.value == "" ) {
      alert("{t}Please enter your email address before submitting.{/t}");
      //JQ document.dropoff.senderEmail.focus();
      $('#senderEmail').trigger('focus');
      return false;
    }
  }
{else}
  if ( document.dropoff.req.value == "" ) {
    alert("{t}Please enter your Request Code before submitting.{/t}");
    //JQ document.dropoff.req.focus();
    $('#req').trigger('focus');
    return false;
  }
{/if}
  // If not caught by now, allow submission
  return true;
}

function submitform() {
  if (validateForm()) { document.dropoff.submit(); }
}

function whichForm(b)
{
  if (b == "yes") {
    // They have a request code
    $(".fullFormText").css("display", "none");
    $(".reqFormText").css("display", "");
    $(".rconfirmbutton").css("display", "none");
    $(".rnextbutton").css("display", "");
    //JQ $('#req').focus();
    $('#req').trigger('focus');
    $("#yes").addClass("UD_buttonchosen");
    $("#no").removeClass("UD_buttonchosen");
    $fullFormTextVisible = false;
  } else {
    // Show them the main form
    $(".reqFormText").css("display", "none");
    $(".fullFormText").css("display", "");
    $(".rnextbutton").css("display", "none");
    $(".rconfirmbutton").css("display", "");
{if $isAuthorizedUser}
    //JQ $('#senderOrganization').focus();
    $('#senderOrganization').trigger('focus');
{else}
    //JQ $('#senderName').focus();
    $('#senderName').trigger('focus');
{/if}
    $("#yes").removeClass("UD_buttonchosen");
    $("#no").addClass("UD_buttonchosen");
    // Wipe the req field
    $("#req").val("");
    $fullFormTextVisible = true;
  }
}

//-->
</script>
  <form name="dropoff" id="dropoff" method="post"
      action="{$zendToURL}{call name=hidePHPExt t='verify.php'}"
      enctype="multipart/form-data" onsubmit="return validateForm();">
      <input type="hidden" name="Action" value="verify"/>
      <table border="0" cellpadding="4">

        <tr><td width="100%">
          <table class="UD_form" border="0" width="100%" cellpadding="4">
            <tr class="UD_form_header"><td colspan="2">
{if ! $allowUploads}
              <h4>{t}Your Request Code{/t}</h4>
            </td></tr>
            <tr>
              <td align="right"><label for="req">{t}Request Code{/t}:</label></td>
              <td width="60%"><input type="text" id="req" name="req" size="45" value="" class="UITextBox" /></td>
            </tr>
            <tr class="footer"><td colspan="2" align="center">
              {capture assign="next_t"}{t}Next{/t}{/capture}{call name=button relative=FALSE href="javascript:submitform();" text=$next_t}
            </tr>
{else} {* $allowUploads *}
              <h4>{t}Information about the Sender{/t}</h4>
            </td></tr>
  {if $verifyFailed}
            <tr><td colspan="2"><strong>{t}You did not complete the form, or you failed the "Am I A Real Person?" test.{/t}</strong></td></tr>
  {/if}
            <tr><td colspan="2">{t escape=no}Have you been given a "<strong>Request Code</strong>"?{/t}&nbsp;&nbsp; 
              <a class="greyButton" style="float:none" id="yes" href="javascript:whichForm('yes');">{t}Yes{/t}</a>
              <a class="greyButton UD_buttonchosen" style="float:none" id="no" href="javascript:whichForm('no');">{t}No{/t}</a>
            </td></tr>
            <tr><td colspan="2"><hr style="width: 80%;"/></td></tr>
            <tr class="reqFormText">
              <td align="right"><label for="req">{t}Request Code{/t}:</label></td>
              <td width="60%"><input type="text" id="req" name="req" size="45" value="" class="UITextBox" /></td>
            </tr>

            <tr class="fullFormText">
              <td align="right"><label for="senderName">{t}Your name{/t}:</label></td>
  {if $isAuthorizedUser}
              <td width="60%"><input type="hidden" id="senderName" name="senderName" value="{$senderName}">{$senderName}</td>
  {else}
              <td width="60%" style="white-space:nowrap"><input type="text" id="senderName" name="senderName" size="45" value="{$senderName}" class="UITextBox" /> <font style="font-size:9px">{t}(required){/t}</font></td>
  {/if}
            </tr>

            <tr class="fullFormText">
              <td align="right"><label for="senderOrganization">{t}Your organization{/t}:</label></td>
              <td width="60%" style="white-space:nowrap"><input type="text" id="senderOrganization" name="senderOrganization" size="45" value="{$senderOrg}"/> <!-- <font style="font-size:9px">{t}(required){/t}</font> --></td>
            </tr>
            <tr class="fullFormText">
              <td align="right"><label for="senderEmail">{t}Your email address{/t}:</label></td>
  {if $isAuthorizedUser}
              <td width="60%"><input type="hidden" id="senderEmail" name="senderEmail" value="{$senderEmail}">{$senderEmail}</td>
  {else}
              <td width="60%" style="white-space:nowrap"><input type="text" id="senderEmail" name="senderEmail" size="45" value="{$senderEmail}" class="UITextBox" /> <font style="font-size:9px">{t}(required){/t}</font></td>
  {/if}
            </tr>

  {if ! $isAuthorizedUser}
            <tr>
              <td colspan="2" align="center">
    {if ! $recaptchaDisabled && ! $invisibleCaptcha}
                <br />{t escape=no}To confirm that you are a <em>real</em> person (and not a computer), please complete the quick challenge below:{/t}<br />&nbsp;<br />
                <div id="google-recaptcha" name="google-recaptcha"></div>
    {/if}
                <br/>
    {if $confirmExternalEmails}
                <div class="rconfirmbutton">
                  {t}I now need to send you a confirmation email.{/t}<br />
                  {t}When you get it in a minute or two, click on the link in it.{/t}
                </div>
    {/if}
              </td>
            </tr>

            <tr class="footer"><td colspan="2" align="center">
    {if $invisibleCaptcha}
              <table class="UD_textbutton">
                <tr valign="middle">
                  <td class="UD_textbutton_left"><a class="UD_textbuttonedge" href="javascript:submitform();">&nbsp;</a></td>
                  <td class="UD_textbutton_content" align="center"><span class="rconfirmbutton"><button {$recaptchaHTML}>{if $confirmExternalEmails}{t}Send confirmation{/t}{else}{t}Next{/t}{/if}</button></span><span class="rnextbutton" style="display:none"><button {$recaptchaHTML}>{t}Next{/t}</button></span></td>
                  <td class="UD_textbutton_right"><a class="UD_textbuttonedge" href="javascript:submitform();">&nbsp;</a></td>
                </tr>
              </table>
    {else} {* Visible captcha *}
              <table class="UD_textbutton">
                <tr valign="middle">
                  <td class="UD_textbutton_left"><a class="UD_textbuttonedge" href="javascript:submitform();">&nbsp;</a></td>
                  <td class="UD_textbutton_content" align="center"><a class="UD_textbutton" href="javascript:submitform();"><span class="rconfirmbutton">{if $confirmExternalEmails}{t}Send confirmation{/t}{else}{t}Next{/t}{/if}</span><span class="rnextbutton" style="display:none">{t}Next{/t}</span></a></td>
                  <td class="UD_textbutton_right"><a class="UD_textbuttonedge" href="javascript:submitform();">&nbsp;</a></td>
                </tr>
              </table>
    {/if} {* $invisibleCaptcha *}
            </tr>
  {else} {* they are an authorised user, so no captcha *}
            <tr class="footer"><td colspan="2" align="center">
              {capture assign="next_t"}{t}Next{/t}{/capture}{call name=button relative=FALSE href="javascript:submitform();" text=$next_t}
            </tr>

  {/if} {* $isAuthorizedUser *}
{/if} {* $allowUploads *}

          </table>
        </td></tr>

      </table>
</form>

{* if they are allowed to upload, accelerate the form filling *}
<script type="text/javascript">
<!--
{if $allowUploads}
  // Set the focus to the organization, and let them
  // click Next by pressing Return.
  $(document).ready(function() {
    whichForm('no'); // assume no request code by default
  // If logged in, submit if Return pressed in Org field
  // If not logged in, submit if Return pressed in Email field
  {if $isAuthorizedUser}
    $('#senderOrganization').on('keypress', function (e) {
  {else}
    $('#senderEmail').on('keypress', function (e) {
  {/if}
      var key = e.which;
      if (key == 13) { // Return
        e.preventDefault();
        if (validateForm()) { document.dropoff.submit(); }
        return false;
      }
    });
  });
{else} {* only the request code box is visible *}
  // Set focus to the request code (only field)
  $(document).ready(function() {
    $('#req').trigger('focus');
  });
{/if}
-->
</script>

{include file="footer.tpl"}
