<html>
<head>
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
  <style>
    a { color: #67709c; font-weight: bold; text-decoration: none; }
    #email-footer, #email-header { background-color: #cbcccd; }
    {fetch file="../www/css/local.css"}
    th, tr, td { padding: 0px; border: none; color: black; }
    img { border: none; }
  </style>
</head>
{function name=hidePHPExt t=""}{if $hidePHP}{$t|replace:'.php':''}{else}{$t}{/if}{/function}
{fetch file="../www/images/email/top-left.png" assign="topleft"}
{fetch file="../www/images/email/top-right.png" assign="topright"}
<body style="font-family: 'Roboto Sans', Helvetica, Verdana, Arial, sans-serif; font-size: 10pt; margin:0;">
<table style="width: 100%; border-collapse: collapse; border: none;">
  <tr id="email-header">
    <td style="width: 10px;"></td>
    <td style="vertical-align: center; text-align: right; padding-right: 10px; height: 100px;"><a href="{$zendToURL}">{include "email_logo_html.tpl"}</a></td>
    <td style="width: 10px;"></td>
  </tr>
  <tr style="background-color: white; padding: 0px; vertical-align: top; font-size: 0px;">
    <td style="padding: 0px; text-align: left; font-size: 0px;"><img style="display:block;" src="data:image/png;base64,{$topleft|base64_encode}" width="10" height="10"></td>
    <td style="background-color: white; font-size: 0px;">&nbsp;</td>
    <td style="padding: 0px; text-align: right; font-size: 0px;"><img style="display:block;" src="data:image/png;base64,{$topright|base64_encode}" width="10" height="10"></td>
  </tr>
  <tr style="background-color: white;">
    <td colspan="3">&nbsp;</td>
  </tr>
  <tr style="background-color: white;">
    <td></td>
    <td>
