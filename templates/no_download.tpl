{$thisTemplate=$smarty.template}{include file="header.tpl"}

{if ! $wasDownloaded }
<div id="error">
  <p>
    <i class="fas fa-exclamation-circle fa-fw"></i> {t}No file was chosen for download.{/t}
  </p>
</div>
{/if}

{include file="footer.tpl"}
