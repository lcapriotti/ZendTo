{$thisTemplate=$smarty.template}{include file="header.tpl"}

<script type="text/javascript">
<!--
function submitform() {
  return document.submitform.submit();
}
//-->
</script>

  <form name="submitform" method="post" action="{$zendToURL}{call name=hidePHPExt t='pickup.php'}">
      <input type="hidden" name="Action" value="Pickup"/>
      <input type="hidden" name="claimID" value="{$claimID}"/>
      <input type="hidden" name="claimPasscode" value="{$claimPasscode}"/>
      <input type="hidden" name="emailAddr" value="{$emailAddr}"/>
      <input type="hidden" name="auth" value="{$auth}"/>


{if $invisibleCaptcha}
<div class="center"><button {$recaptchaHTML}>{t}Pick-up Files{/t}</button></div>
{else}
      <table border="0" cellpadding="4">
            <tr class="UD_form_header"><td>
              <h4>{t}Please prove you are a person{/t}</h4>
            </td></tr>
            <tr>
              <td align="center">
                {t escape=no}To confirm that you are a <em>real</em> person (and not a computer), please complete the quick challenge below then click "Pick-up Files":{/t}<br />&nbsp;<br />
                <div id="google-recaptcha" name="google-recaptcha"></div>
              </td>
            </tr>
            <tr class="footer">
              <td align="center">
                <div class="center"><button onclick="submitform();">{t}Pick-up Files{/t}</button></div>
              </td>
            </tr>
      </table>
{/if}

  </form>

{include file="footer.tpl"}
