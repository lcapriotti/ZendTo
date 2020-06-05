{* If hidePHP is set, remove the .php from what we're passed. *}
{* Output it either way. *}
{function name=hidePHPExt t=""}{if $hidePHP}{$t|replace:'.php':''}{else}{$t}{/if}{/function}

{function name=button href="" text="&nbsp;" width="" admin="" relative=TRUE dotphp=FALSE}
  {if $width ne ""}
    {$width=" width=\"$width\""}
  {/if}
  {if $admin}
    {$admin="_admin"}
  {else}
    {$admin=""}
  {/if}
  {if $relative}
    {$href = "$zendTOURL$href"}
  {/if}
  <table{$width} class="UD_textbutton">
    <tr valign="middle">
{*      <td class="UD_textbutton_left{$admin}"><a class="UD_textbuttonedge" href="{call name=hidePHPExt t=$href}">&nbsp;</a></td> *}
      <td class="UD_textbutton_content{$admin}" align="center"><a class="UD_textbutton{$admin}" href="{if $dotphp}{$href}{else}{call name=hidePHPExt t=$href}{/if}">{$text}</a></td>
{*      <td class="UD_textbutton_right{$admin}"><a class="UD_textbuttonedge" href="{call name=hidePHPExt t=$href}">&nbsp;</a></td> *}
    </tr>
  </table>
{/function}

{function name=footerButtons}
  {capture assign="servicetitle_t"}{t 1=#ServiceTitle#}Return to the %1 main menu{/t}{/capture}
  {capture assign="logout_t"}{t}Logout{/t}{/capture}
  <table border="0" cellpadding="4"><tr>
  <td>{call name=button href=$zendToURL text=$servicetitle_t}</td>
  {if $isAuthorizedUser}<td>{call name=button href="{$zendToURL}?action=logout" text=$logout_t}</td>{/if}</tr>
  </table>
{/function}

