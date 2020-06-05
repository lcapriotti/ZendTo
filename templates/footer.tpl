<!-- End page content -->

  </div>
</div>

<div id="footer">
  {capture assign="year"}{$smarty.now|date_format:'%Y'}{/capture}
  <span style="white-space: nowrap">{t 1=$ztVersion}Version %1{/t}&nbsp;|&nbsp;{t escape=no 1=$year}Copyright &copy; %1{/t} ZendTo&nbsp;|&nbsp;<a href="{call name=hidePHPExt t='about.php'}">{t 1=#ServiceTitle#}About %1{/t}</a></span>
  {if $whoAmI ne ""}<br/><span style="white-space: nowrap" title="{$whoAmIuid|escape} &lt;{$whoAmImail|escape}&gt;">{t escape=no 1=$whoAmI}You are currently logged in as <em>%1</em>{/t}</span>{/if}
  <br/>
  <span style="white-space: nowrap">{t 1="ZendTo" escape=no}This service is powered by a copy of <a href="http://zend.to/" target="_blank">%1</a>{/t}</span>
</div>

</body>
</html>
