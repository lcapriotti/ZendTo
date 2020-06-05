    </td>
    <td></td>
  </tr>
  <tr style="background-color: white;">
    <td colspan="3">&nbsp;</td>
  </tr>
  {fetch file="../www/images/email/bottom-left.png"  assign="bottomleft"}
  {fetch file="../www/images/email/bottom-right.png" assign="bottomright"}
  <tr style="background-color: white; padding: 0px; vertical-align: bottom; font-size: 0px;">
    <td style="padding: 0px; text-align: left; font-size: 0px;"><img style="display:block;" src="data:image/png;base64,{$bottomleft|base64_encode}" width="10" height="10"></td>
    <td style="font-size: 0px;"></td>
    <td style="padding: 0px; text-align: right; font-size: 0px;"><img style="display:block;" src="data:image/png;base64,{$bottomright|base64_encode}" width="10" height="10"></td>
  </tr><tr>
    {capture assign="year"}{$smarty.now|date_format:'%Y'}{/capture}
    <td id="email-footer" colspan="3" style="height: 50px; text-align: center; white-space: nowrap;">{t escape=no 1=$year}Copyright &copy; %1{/t}  ZendTo | <a href="{$zendToURL}{call name=hidePHPExt t='about.php'}">{t 1=#ServiceTitle#}About %1{/t}</a><br/>{t 1="ZendTo" escape=no}This service is powered by a copy of <a href="http://zend.to/" target="_blank">%1</a>{/t}</td>
  </tr>
</table>
</body>
</html>
