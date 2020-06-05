{function name=hidePHPExt t=""}{if $hidePHP}{$t|replace:'.php':''}{else}{$t}{/if}{/function}{t 1=#ServiceTitle#}This is an automated message sent to you by the %1 service.{/t}

{t}Name{/t}: {$senderName}
{t}Organization{/t}: {$senderOrg}
{t}Email{/t}: {$senderEmail}

{t}You have asked us to send you this message so that you can drop-off some files for someone.{/t}

{t}IGNORE THIS MESSAGE IF YOU WERE NOT IMMEDIATELY EXPECTING IT!{/t}

{t}Otherwise, continue the process by clicking the following link (or copying and pasting it into your web browser):{/t}

  {$URL}

