{$thisTemplate=$smarty.template}{include file="header.tpl"}

{if $cameFromEmail}
<h4>{t}Please enter the claim id and claim passcode that were sent to you.{/t}
{else}
<h4>{t}Please enter the claim id and claim passcode.{/t}
{/if}
{* {if $isAuthorizedUser}
{t}If the sender gave you a passcode for the claim, please enter it.{/t}
{/if} *}
</h4>

  <form name="pickup" method="post" action="{$zendToURL}{call name=hidePHPExt t='pickup.php'}">
    <input type="hidden" name="auth" value="{$auth}"/>
    <input type="hidden" name="emailAddr" value="{$emailAddr}"/>
    <table class="UD_form" cellpadding="4" width="100%">
      <tr>
        <td align="right" width="50%"><strong>{t}Claim ID{/t}:</strong></td>
        <td align="left" width="50%"><input type="text" id="claimID" name="claimID" size="25" value="{$claimID}"/></td>
      </tr>
      <tr>
        <td align="right" width="50%"><strong>{t}Claim Passcode{/t}:</strong></td>
        <td align="left" width="50%"><input type="text" name="claimPasscode" size="25" value=""/></td>
      </tr>
    </table>
    <div class="center"><button id="pickup" onclick="document.dropoff.submit();">{t}Pick-up Files{/t}</button></div>

  </form>

{include file="footer.tpl"}
