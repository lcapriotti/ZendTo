{$thisTemplate=$smarty.template}{include file="header.tpl"}

{if count($lockedout)>0}

<script type="text/javascript">
<!--

function submitUnlock()
{
  return document.unlock.submit();
}

function tickAll()
{
  if (document.unlock.unlockall.checked) {
    val = true;
  } else {
    val = false;
  }

  {for $i=0; $i<count($lockedout); $i++}
    document.unlock.unlocktick_{$i}.checked = val;
  {/for}
  document.unlock.unlockall.checked = val;
}

//-->
</script>


<form name="unlock" method="post" action="{$zendToURL}{call name=hidePHPExt t='unlock.php'}">
&nbsp;<br />

<table class="UD_form" cellpadding="4">
  <tr valign="middle" class="UD_form_header">
    <td class="UD_form_lined">{t}Unlock{/t}</td>
    <td class="UD_form_lined">{t 1=#OrganizationShortName#}%1 Username{/t}</td>
    <td class="UD_form_lined">{t}Name{/t}</td>
  </tr>

{for $i=0;$i<count($lockedout);$i++}
  <tr valign="middle" class="UD_form_lined">

    <td align="center" class="UD_form_lined"><input type="checkbox" name="unlocktick_{$i}" id="unlocktick_{$i}" value="{$lockedout[$i]}"/></td>
    <td class="UD_form_lined">{$lockedout[$i]}</td>
    <td class="UD_form_lined">{$lockednames[$i]}</td>

  </tr>
{/for}

  <tr valign="middle" class="UD_form_lined">
    <td align="center" class="UD_form_lined"><input type="checkbox" name="unlockall" id="unlockall" onClick="tickAll();"></td>
    <td colspan="2" class="UD_form_lined"><label for="unlockall">{t}Select all{/t}</label></td>
  </tr>
</table>

<input type="hidden" name="action" value="unlock" />
<input type="hidden" name="unlockMax" id="unlockMax" value="{$unlockMax}" />

&nbsp;<br />
{call name=button href="javascript:submitUnlock();" text="{t}Unlock selected users{/t}"}

</form>

{else}
<div id="error">
  <p>
    <i class="fas fa-exclamation-circle fa-fw"></i> {t}There are no locked users.{/t}
  </p>
</div>
{/if}

{include file="footer.tpl"}
