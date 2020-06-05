{$thisTemplate=$smarty.template}{include file="header.tpl"}
<script type="text/javascript">
$(document).ready(function() {
  {* disable the language picker *}
  $('#localeButton').prop('onclick', null);
  $('#localeButton').removeClass('dropdown-has-hover');
});
</script>

<p>{t 1=#ServiceTitle#}Now wait for the email message from the %1 service to arrive and click on the link in it.{/t}</p>
<p>{t}You may close this window.{/t}</p>
<p>{t escape=no 1=$zendToURL}You will be directed to the <a href="%1">main menu</a> in a moment.{/t}</p>

{include file="footer.tpl"}
