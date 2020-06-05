{$thisTemplate=$smarty.template}{include file="header.tpl"}

{if $isLocalIP}
<h4>{t}Please login above.{/t}</h4>
{else}
<br><br><br>
<center>
  <form name="login" method="post" action=".">
  <input type="hidden" name="action" value="login">
  <table class="UD_form" cellpadding="4" width="100%">
    <tr class="UD_form_header">
      <td colspan="2" align="center"><h4><i class="fas fa-lock"></i>&nbsp;&nbsp;{t}Authentication{/t}</h4></td>
    </tr>
    <tr>
      <td align="right" width="50%"><strong>{t 1=#OrganizationShortName#}%1 Username{/t}:</strong></td>
      <td align="left" width="50%"><input type="text" id="uname" name="uname" size="25" autocomplete="username" value=""/></td>
    </tr>
    <tr>
      <td align="right" width="50%"><strong>{t}Password{/t}:</strong></td>
      <td align="left" width="50%"><input type="password" id="passwordField" name="password" size="25" autocomplete="current-password" value=""/></td>
    </tr>
    <tr class="footer">
      <td colspan="2" align="center">
        <script type="text/javascript">
          bindEnter($('#passwordField'), function(){ submitform() });
          function submitform() { document.login.submit(); }
          $(document).ready(function() { $('#uname').trigger('focus'); });
        </script>
        {capture assign="login_t"}{t}Login{/t}{/capture}{call name=button relative=FALSE href="javascript:submitform();" text=$login_t}
      </td>
    </tr>
  </table>
  </form>
</center>
{/if}

{include file="footer.tpl"}
