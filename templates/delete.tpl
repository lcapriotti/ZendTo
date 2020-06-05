{$thisTemplate=$smarty.template}{include file="header.tpl"}

{if $success}
  <div id="info">
    <p><i class="fas fa-info-circle fa-fw"></i> {t 1=$claimID}The drop-off with claim ID %1 was successfully removed.{/t}</p>
  </div>
{else}
  <div id="error">
    <p><i class="fas fa-exclamation-circle fa-fw"></i> {t}Unable to remove the drop-off{/t}{if $claimID!=''} %1{/if}.
{if $claimID==''}{t}You may have already deleted it.{/t}
{else}{t}Please notify the system administrator.{/t}
{/if}
    </p>
  </div>
{/if}

{include file="footer.tpl"}
