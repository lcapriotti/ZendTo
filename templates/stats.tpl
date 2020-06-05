{$thisTemplate=$smarty.template}{include file="header.tpl"}

{if $isAuthorizedUser && $isStatsUser}

<blockquote>
  <form name="periodForm" method="get" action="{$zendToURL}{call name=hidePHPExt t='stats.php'}">
  <table border="0">
    <tr>
      <td>{t}View stats for the{/t}</td>
      <td>
        <select name="period" onchange="return document.periodForm.submit();">
          <option value="week"{if $period eq 7} selected="selected"{/if}>{t}past week{/t}</option>
          <option value="month"{if $period eq 30} selected="selected"{/if}>{t}past month{/t}</option>
          <option value="90days"{if $period eq 90} selected="selected"{/if}>{t}past 90 days{/t}</option>
          <option value="year"{if $period eq 365} selected="selected"{/if}>{t}past year{/t}</option>
          <option value="decade"{if $period eq 3650} selected="selected"{/if}>{t}past 10 years{/t}</option>
        </select>
      </td>
    </tr>
  </table>
  </form>

  <hr/>

  <table border="0">
    <tr>
      <td><strong>{t}Number of drop-offs made (checked daily){/t}</strong></td>
    </tr>
    <tr>
      <td><img src="{$zendToURL}{call name=hidePHPExt t='graph.php'}?m=dropoff_count&p={$period}" alt="[dropoff counts]"/></td>
    </tr>

    <tr>
      <td><strong>{t}Total amount of data dropped off (checked daily){/t}</strong></td>
    </tr>
    <tr>
      <td><img src="{$zendToURL}{call name=hidePHPExt t='graph.php'}?m=total_size&p={$period}" alt="[total dropoff bytes]"/></td>
    </tr>

    <tr>
      <td><strong>{t}Total files dropped off (checked daily){/t}</strong></td>
    </tr>
    <tr>
      <td><img src="{$zendToURL}{call name=hidePHPExt t='graph.php'}?m=total_files&p={$period}" alt="[total dropoff files]"/></td>
    </tr>

    <tr>
      <td><strong>{t}File count per drop-off (checked daily){/t}</strong></td>
    </tr>
    <tr>
      <td><img src="{$zendToURL}{call name=hidePHPExt t='graph.php'}?m=files_per_dropoff&p={$period}" alt="[files per dropoff]"/></td>
    </tr>
  </table>
</blockquote>


{else}

<div id="error">
  <p>
    <i class="fas fa-exclamation-circle fa-fw"></i> {t}This is available to administrators only.{/t}
  </p>
</div>

{/if}

{include file="footer.tpl"}
