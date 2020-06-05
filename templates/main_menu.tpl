{$thisTemplate=$smarty.template}{include file="header.tpl"}

<!--[if lt IE 9]>
<script type="text/javascript">
  alert("{t}You are using an old unsupported version of Internet Explorer.{/t}\n{t}This site will not work correctly with this web browser.{/t}\n{t}Please use a better web browser, such as Google Chrome or Firefox, or at least Internet Explorer 11.{/t}");
</script>
<![endif]-->

{capture assign="login_t"}{t}Login{/t}{/capture}
{capture assign="dropoff_t"}{t}Drop-off{/t}{/capture}
{capture assign="em_dropoff_t"}<em>{t}Drop-off{/t}</em>{/capture}
{capture assign="pickup_t"}{t}Pick-up{/t}{/capture}
{capture assign="request_t"}{t}Request a Drop-off{/t}{/capture}
{capture assign="em_request_t"}<em>{t}Request a Drop-off{/t}</em>{/capture}
{capture assign="showall_t"}{t}Show All Drop-offs{/t}{/capture}
{capture assign="unlock_t"}{t}Unlock Users{/t}{/capture}
{capture assign="stats_t"}{t}System Statistics{/t}{/capture}
{capture assign="syslog_t"}{t}System Log{/t}{/capture}


{if $isAuthorizedUser}
  <!-- User has logged in -->
<table border="0" class="homeButtons">
  <tr>
    <td colspan="2">&nbsp;<!-- {$isLocalIP} --></td>
  </tr>
  <tr>
    <td colspan="2"><h4>{t}You may perform the following activities:{/t}</h4></td>
  </tr>
  <tr>
    <td>{call name=button href="verify.php" text=$dropoff_t width="100%"}</td>
    <td class="UD_nav_label">{t escape=no}Drop-off (<em>upload</em>) a file for someone else.{/t}</td>
  </tr>
  <tr>
    <td>{call name=button href="req.php" text=$request_t width="100%"}</td>
    <td class="UD_nav_label">{t escape=no}Ask another person to send you some files.{/t}</td>
  </tr>
  <tr>
    <td>{call name=button href="pickup.php" text=$pickup_t width="100%"}</td>
    <td class="UD_nav_label">{t escape=no}Pick-up (<em>download</em>) a file dropped-off for you.{/t}</td>
  </tr>
{if $isStatsUser}
        <tr><td colspan="2">&nbsp;</td></tr>
{/if}
{if $isAdminUser}
  <tr>
    <td>{call name=button href="pickup_list_all.php" text=$showall_t width="100%" admin=$isAdminUser}</td>
    <td class="UD_nav_label">{t}View all drop-offs in the database{/t} (<em>{t}Administrators only{/t}</em>).</td>
  </tr>
  <tr>
    <td>{call name=button href="unlock.php" text=$unlock_t width="100%" admin=$isAdminUser}</td>
    <td class="UD_nav_label">{t}Unlock locked-out users{/t} (<em>{t}Administrators only{/t}</em>).</td>
  </tr>
{/if}
{if $isStatsUser}
  <tr>
    <td>{call name=button href="stats.php" text=$stats_t width="100%" admin=$isStatsUser}</td>
    <td class="UD_nav_label">{t}View daily statistics for the service{/t} (<em>{t}Administrators only{/t}</em>).</td>
  </tr>
{/if}
{if $isAdminUser}
  <tr>
    <td>{call name=button href="log.php" text=$syslog_t width="100%" admin=$isAdminUser}</td>
    <td class="UD_nav_label">{t}View log file{/t} (<em>{t}Administrators only{/t}</em>).</td>
  </tr>
{/if}

  <tr><td colspan="2">&nbsp;</td></tr>
</table>

{else}
  <!-- Not logged in. -->
<table border="0" class="homeButtons">
{if $isLocalIP && !$SAML}
  <tr><td colspan="2"><h4>{t escape=no 1=#OrganizationShortName#}If you are a %1 user, you should login above to avoid having to verify your email address,<br/>and be able to drop-off files to non-%1 users.{/t}</h4></td></tr>
{else}
  {if $allowExternalLogins || $SAML}
  <tr><td colspan="2"><h4>{t escape=no 1=#OrganizationShortName#}If you are a %1 user, you may login here:{/t}</h4></td></tr>
  <tr>
    <td>{call name=button href=$loginUrl text=$login_t width="100%" dotphp=$SAML}</td>
    <td class="UD_nav_label">{if $SAML}{t escape=no 1=#OrganizationShortName#}<strong>%1 users should login first.</strong>{/t}{else}{t escape=no 1=#OrganizationShortName#}<strong>Avoid having to verify your email address</strong>,<br/>and drop-off files to non-%1 users.{/t}{/if}</td>
  </tr>
  <tr><td colspan="2">&nbsp;</td></tr>
  {/if}
{/if}
  {if $allowExternaUploads || $allowExternalPickups}
  <tr><td colspan="2"><h4>{t escape=no}Anyone may perform the following activities:{/t}</h4></td></tr>
  {if $allowExternalUploads}
  <tr>
    <td>{call name=button href="verify.php" text=$dropoff_t width="100%"}</td>
    <td class="UD_nav_label">{t escape=no 1=#OrganizationShortName#}Drop-off (<em>upload</em>) a file for a %1 user (<strong>email verification required</strong>).{/t}</td>
  </tr>
  {/if}
  {if $allowExternalPickups}
  <tr>
    <td>{call name=button href="pickup.php" text=$pickup_t width="100%"}</td>
    <td class="UD_nav_label">{t escape=no}Pick-up (<em>download</em>) a file dropped off for you.{/t}</td>
  </tr>
  {/if}
  <tr><td colspan="2">&nbsp;</td></tr>
  {/if}
</table>

{/if}

<div id="info">
  <table class="UD_error" width="100%">
  <tr class="ud_error_message">
    <td><i class="fas fa-info-circle fa-fw"></i></td>
    <td>{t escape=no 1=#OrganizationShortName# 2=#OrganizationShortType#}%1 users: you may login with your username and password and send files to anyone, in or out of %2{/t}.</td>
  </tr>
  <tr class="ud_error_message">
    <td><i class="fas fa-fw"></i></td>
    <td>{t escape=no 1=#OrganizationShortName# 2=$dropoff_t 3=$em_dropoff_t}Non-%1 users: you cannot log in, but can still send files to %1 users if you know their email address. Start by clicking the "%3" button.{/t}</td>
  </tr>
  <tr class="ud_error_message">
    <td><i class="fas fa-fw"></i></td>
    <td>{t escape=no 1=#OrganizationShortName# 2=#OrganizationShortType# 3=$em_request_t}%1 users who wish someone outside %2 to send them files, can make it a lot easier for them by logging in and clicking "%3". That saves the other person having to prove who they are.{/t} {t 1=$requestTTL}The request created will be valid for %1.{/t}<br/>&nbsp;</td>
  </tr>
  <tr class="ud_error_message">
    <td><i class="fas fa-fw"></i></td>
    <td>{t 1=#ServiceTitle# 2=$maxFileSize 3=#OrganizationShortType#}%1 is a service to make it easy for you to move files, including large files up to %2, in and out of %3.{/t}<br/>&nbsp;</td>
  </tr>
  <tr class="ud_error_message">
    <td><i class="fas fa-question-circle fa-fw"></i></td>
    <td><a href="{call name=hidePHPExt t='security.php'}">{t 1=#ServiceTitle#}How secure is %1?{/t}</a></td>
  </tr>
  </table>
</div>
<br/>
<div id="error">
  <table class="UD_error" width="100%">
  <tr class="ud_error_message">
    <td style="width:5px"><i class="fas fa-exclamation-circle"></i></td>
    <td>{t 1=#ServiceTitle# 2=$keepForDays}Files are automatically deleted from %1 %2 days after you upload them.{/t}</td>
  </tr>
  </table>
</div>

{include file="footer.tpl"}
