{$thisTemplate=$smarty.template}{include file="header.tpl"}

<div id="info">
  <p>
    <i class="fas fa-info-circle fa-fw"></i> {t 1=$count 2=#ServiceTitle#}Last %1 lines of %2 log, most recent first{/t}
  </p>
</div>

<div class="mono"><pre>{$log}</pre></div>

{include file="footer.tpl"}
