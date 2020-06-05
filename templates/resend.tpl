{$thisTemplate=$smarty.template}{include file="header.tpl"}

{if $success}
  <div id="info">
    <p>
      <i class="fas fa-info-circle fa-fw"></i> {t}The drop-off was successfully re-sent to its recipients.{/t}
    </p>
  </div>
{else}
  <div id="error">
    <p>
      <i class="fas fa-exclamation-circle fa-fw"></i> {t}Unable to re-send the drop-off.{/t} {t}Please notify the system administrator.{/t}
    </p>
  </div>
{/if}

{include file="footer.tpl"}
