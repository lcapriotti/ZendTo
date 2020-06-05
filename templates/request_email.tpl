{function name=hidePHPExt t=""}{if $hidePHP}{$t|replace:'.php':''}{else}{$t}{/if}{/function}{if $toName}{$toName},

{/if}{if $fromOrg}{t 1=$fromName t=$fromOrg}This is a request from %1 of %2.{/t}{else}{t 1=$fromName}This is a request from %1.{/t}{/if}

{t}Please click on the link below and drop off the file or files I have requested.{/t}
{t 1=$requestTTL}The link is only valid for %1 from the time of this email.{/t}
{if $encrypted}{t 1=$fromName}All files you upload will be automatically encrypted.{/t}
{/if}{if $note}{t}More information is in the note below.{/t}{/if}

{$URL}

{t 1=$fromName}If you wish to contact %1, just reply to this email.{/t}

{if $note}* {t}Note{/t} *
{$note}
{/if}

-- 
{$fromName}
{$fromEmail}
{$fromOrg}
