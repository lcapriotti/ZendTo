{$thisTemplate=$smarty.template}{include file="header.tpl"}

{assign var="tick" value='<i class="fas fa-fw fa-check"></i>'}
{assign var="cross" value='<i class="fas fa-fw fa-times"></i>'}
{capture assign="picked_up"}{t}Picked up{/t}{/capture}
<script type="text/javascript" src="js/jquery.balloon.min.js"></script>

<script type="text/javascript">
<!--
$(document).ready(function() {
    // This enables date sorting with the same spec as the date_format below
    $.fn.dataTable.moment('YYYY-MM-DD HH:mm:ss');
    $('#pickup_list').DataTable( {
       "pagingType": "full_numbers",
       "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "{t}All{/t}"] ],
       "language": {
         "search":       "{t}Search:{/t}",
         "lengthMenu":   "{t}Show _MENU_ entries{/t}",
         "info":         "{t}Showing _START_ to _END_ of _TOTAL_ entries{/t}",
         "infoEmpty":    "{t}Showing 0 to 0 of 0 entries{/t}",
         "infoFiltered": "{t}(filtered from _MAX_ total entries){/t}",
         "infoPostFix":  "",
         "zeroRecords":  "{t}No matching records found{/t}",
         "emptyTable":   "{t}No data available in table{/t}",
         "paginate": {
           first:    '<i class="fas fa-angle-double-left fa-fw"></i>',
           previous: '<i class="fas fa-angle-left fa-fw"></i>',
           next:     '<i class="fas fa-angle-right fa-fw"></i>',
           last:     '<i class="fas fa-angle-double-right fa-fw"></i>'
         },
         aria: {
           "paginate": {
             first:    '{t}First{/t}',
             previous: '{t}Previous{/t}',
             next:     '{t}Next{/t}',
             last:     '{t}Last{/t}'
           }
         }
       },
       "order":      [[ 3, "desc" ]],
       "columns":    [
         { "title": "{t}Claim ID{/t}", "className": "dt-body-left", "width": "5%" },
         { "title": "{t}Sender{/t}",   "className": "dt-body-left" },
         { "title": "{t}Size{/t}",     "className": "dt-body-right", "width": "5%" },
         { "title": "{t}Created{/t}",  "className": "dt-body-center" },
         { "title": "{t}Picked up{/t} <i id='pickedup-balloon' name='pickedup-balloon' class='fas fa-info-circle' style='vertical-align:middle'></i>","className": "dt-body-center", "width": "5%" },
         { "title": "{t}Encrypted{/t} <i id='encrypted-balloon' name='encrypted-balloon' class='fas fa-info-circle' style='vertical-align:middle'></i>","className": "dt-body-center", "width": "5%" },
       ]
    } );
    $('.dataTable').on('click', 'tbody td', function() {
      doPickup($(this).parent().children().first().text());
    });
    $(window).on('unload', function() {
      $('.dataTable').off('click', 'tbody td');
    });
    // All the balloon tooltips
    $('#pickedup-balloon').balloon({
      position: "top left",
      html: true,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: '{t escape=no 1=$tick 2=$picked_up}A "%1" in the "%2" column cannot <em>guarantee</em> that any file has been completely downloaded successfully.{/t}<br />{t escape=no 1=$cross}But a "%1" does mean it has <em>not</em> been downloaded successfully.{/t}',
      showAnimation: function (d, c) { this.fadeIn(d, c); }
    });
    $('#encrypted-balloon').balloon({
      position: "top left",
      html: true,
      css: { fontSize: '100%', 'max-width': '40vw' },
      contents: '{t escape=no 1=$tick 2=#ServiceTitle#}%1 = Encrypted by %2{/t}<br />{t escape=no 1=$cross 2=#ServiceTitle#}%1 = Not encrypted by %2{/t}',
      showAnimation: function (d, c) { this.fadeIn(d, c); }
    });
    $('.fas.fa-fw.fa-check').balloon({
      position: "right",
      css: { fontSize: '100%' },
      contents: '{t}Yes{/t}',
      showDuration: 0,
      hideDuration: 0,
      showAnimation: null,
      hideAnimation: null
    });
    $('.fas.fa-fw.fa-times').balloon({
      position: "right",
      css: { fontSize: '100%' },
      contents: '{t}No{/t}',
      showDuration: 0,
      hideDuration: 0,
      showAnimation: null,
      hideAnimation: null
    });
});
//-->
</script>

{if $isAuthorizedUser}

  {if $countDropoffs>0}
<h1>{t}Inbox{/t}</h1>
<p>{t}Click on a drop-off to view the information and files for that drop-off.{/t}</p>
<table id="pickup_list" class="display" width="100%">
  <tbody>
    {foreach from=$dropoffs item=d}
  <tr>
    <td class="mono">{$d.claimID}</td>
    <td>{$d.senderName}{if $d.senderOrg != ''}, {$d.senderOrg}{/if}<br/>&lt;{$d.senderEmail}&gt;</td>
    <td data-order="{$d.Bytes}"><span style="white-space: nowrap">{$d.formattedBytes}</span></td>
    <td>{$d.createdDate|date_format:"%Y-%m-%d %H:%M:%S"}</td>
    <td data-order="{$d.numPickups}"><i class="fas fa-fw {($d.numPickups>0)?'fa-check':'fa-times'}"></i></td>
    <td data-order="{($d.isEncrypted)?'1':'0'}"><i class="fas fa-fw {($d.isEncrypted)?'fa-check':'fa-times'}"></i></td>
  </tr>
    {/foreach}
  </tbody>
</table>

  <form name="pickup" method="post" action="{$zendToURL}{call name=hidePHPExt t='pickup.php'}"> <!-- Add target="_blank" if you always want a new tab -->
  <input type="hidden" id="claimID" name="claimID" value=""/>
</form>

  {else}
<div id="error">
  <p>
    <i class="fas fa-exclamation-circle fa-fw"></i> {t}There are no drop-offs available for you at this time.{/t}
  </p>
</div>
  {/if}

{/if}

{include file="footer.tpl"}
