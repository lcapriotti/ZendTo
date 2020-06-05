{$thisTemplate=$smarty.template}{include file="header.tpl"}

<div id="info">
  <table class="UD_error" width="100%">
  <tr class="ud_error_message">
    <td class="UD_error_logo"><i class="fas fa-info-circle fa-fw"></i></td>
    <td>{t}You have been logged out.{/t}</td>
  </tr>
  <tr class="ud_error_message">
    <td class="UD_error_logo"><i class="fas fa-fw"></i></td>
    <td>{t}For better security, you should also exit this browser, or at least close this browser window.{/t}</td>
  </tr>
  <tr class="ud_error_message">
    <td class="UD_error_logo"><i class="fas fa-fw"></i></td>
    <td>{t escape=no 1=$zendToURL}You will be redirected to the <a href="%1">main menu</a> in a moment.{/t}</td>
  </tr>
  </table>
</div>

{include file="footer.tpl"}
