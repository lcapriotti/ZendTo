{$thisTemplate=$smarty.template}{include file="header.tpl"}

{* Inbox *}

{assign var="tick" value='<i class="fas fa-fw fa-check"></i>'}
{assign var="cross" value='<i class="fas fa-fw fa-times"></i>'}
{capture assign="picked_up"}{t}Picked up{/t}{/capture}
<script type="text/javascript" src="js/jquery.balloon.min.js"></script>

<script type="text/javascript">
<!--

var visToReal = []; // map visible column number to real one. -1 ==> empty.

// Update the visToReal map
function mapVisToReal(table) {
  var real=0;
  var visible=0;
  var numColumns = table.init().columns.length;

  // Build the map from visible to real
  while (real < numColumns && visible < numColumns) {
    // Skip the invisible columns
    while (!table.column(real).visible() && real < numColumns)
      real++;
    visToReal[visible] = (real>=numColumns)?-1:real;
    real++;
    visible++
  }
  // Fill the remainder of the map with -1
  while (visible < numColumns) {
    visToReal[visible] = -1;
    visible++;
  }
}

// Replace HTML entities by their real equivalents for CSV
function htmlUnescape(str){
  return str
        .replace(/\<br\/?\>/g, ' ')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

// As above, but also handle linebreaks
function htmlUnescapeAndBR(str){
  return str
        .replace(/\<br\/?\>/g, "; ") // Separator for emails
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

// Given the cell we're converting and the HTML in it,
// convert it to the CSV we're going to export.
function cellToCSV(html, rowNum, colNum, node) {
  // Massage the HTML into the CSV
  switch(visToReal[colNum]) {
    case -1:          // This should never happen.
      return html;
    case 2:           // Recipients
      return htmlUnescapeAndBR(html);
    case 4:           // Size in bytes
      // Export the real size, not the formatted string
      return $(node).attr('data-order');
    case 7:           // picked-up and encrypted
    case 8:           // booleans
      // Reduce the tick-boxes to yes/no
      return html.replace(/^.*times.*$/, 'false')
                 .replace(/^.*check.*$/, 'true');
    default:
      // Otherwise just clean up the HTML
      return htmlUnescape(html);
  }
  return html;
}

$(document).ready(function() {
    // This enables date sorting with the same spec as the date_format below
    $.fn.dataTable.moment('YYYY-MM-DD HH:mm:ss');
    var table = $('#pickup_list').DataTable( {
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
       "order":      [[ 5, "desc" ]],
       "columns":    [
         { "title": "{t}Claim ID{/t}", "className": "dt-body-left", "width": "5%" },
         { "title": "{t}Sender{/t}",   "className": "dt-body-left" },
         { "title": "{t}Recipients{/t}", "className": "dt-body-left", "visible": false },
         { "title": "{t}Subject{/t}",  "className": "dt-body-left", "visible": false },
         { "title": "{t}Size{/t}",     "className": "dt-body-right", "width": "5%" },
         { "title": "{t}Created{/t}",  "className": "dt-body-center" },
         { "title": "{t}Expires{/t}",  "className": "dt-body-center", "visible": false },
         { "title": "{t}Picked up{/t} <i id='pickedup-balloon' name='pickedup-balloon' class='fas fa-info-circle' style='vertical-align:middle'></i>","className": "dt-body-center", "width": "5%" },
         { "title": "{t}Encrypted{/t} <i id='encrypted-balloon' name='encrypted-balloon' class='fas fa-info-circle' style='vertical-align:middle'></i>","className": "dt-body-center", "width": "5%" },
       ],
       dom: '<"hidden"B>lfrtip', // was "clearfix"
       "buttons": [ {
         extend: 'csvHtml5',
         text:   '{t}Export as CSV{/t}',
         filename: '{#ServiceTitle#} {t}Inbox{/t}',
         charset: 'utf-8',
         exportOptions: {
           // Bware: the columns are numbered
           // according to what's visible, not the real
           // column numbers. :-(
           columns: ':visible',
           stripNewline: false,
           stripHtml: true,
           format: {
            body: cellToCSV,
           },
           modifier: {
             selected: null
           }
         }
       } ]
    });
    $('.dataTable').on('click', 'tbody td', function() {
      doPickup(table.row( this ).data()[0]);
    });
    $(window).on('unload', function() {
      $('.dataTable').off('click', 'tbody td');
    });
    // The column visibility toggles
    $('a.toggle-vis').on('click', function (e) {
      e.preventDefault();
      dataColumn = $(this).attr('data-column');
      if (dataColumn < 0) {
        // Show/hide ALL columns (show all unless all shown)
        // Start with TRUE. 'and' it with each column. Gives 'all shown'
        allShown = true;
        table.columns().every( function() {
          allShown = allShown && this.visible();
        });
        // If they were all shown, hide all. Else show all.
        table.columns().every( function() {
          this.visible( ! allShown );
        });
      } else {
        // Show/hide 1 column
        // Get the column API object
        var column = table.column( dataColumn );
        // Toggle the visibility
        column.visible( ! column.visible() );
      }
      // Recalculate the visible=>real map
      mapVisToReal(table);
    });
    // Build the initial visible=>real map
    mapVisToReal(table);
    // The Export button
    $('#exportCSVButton').on('click', function(e) {
      e.preventDefault();
      table.button(0).trigger();
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
<div style="float:right; display:flex">
  <button id="exportCSVButton" class="UD_textbutton">{t}Export as CSV{/t}</button>
</div>

<h1>{t}Inbox{/t}</h1>
<p>{t}Click on a drop-off to view the information and files for that drop-off.{/t}</p>
<p>{t}Show or hide:{/t}
          <a class="toggle-vis" data-column="-1">{t}All{/t}</a>
  &ndash; <a class="toggle-vis" data-column="0">{t}Claim ID{/t}</a>
  &ndash; <a class="toggle-vis" data-column="1">{t}Sender{/t}</a>
  &ndash; <a class="toggle-vis" data-column="2">{t}Recipients{/t}</a>
  &ndash; <a class="toggle-vis" data-column="3">{t}Subject{/t}</a>
  &ndash; <a class="toggle-vis" data-column="4">{t}Size{/t}</a>
  &ndash; <a class="toggle-vis" data-column="5">{t}Created{/t}</a>
  &ndash; <a class="toggle-vis" data-column="6">{t}Expires{/t}</a>
  &ndash; <a class="toggle-vis" data-column="7">{t}Picked up{/t}</a>
  &ndash; <a class="toggle-vis" data-column="8">{t}Encrypted{/t}</a>
</p>

<table id="pickup_list" class="display" width="100%">
  <tbody class="nowrap">
    {foreach from=$dropoffs item=d}
  <tr>
    <td class="mono">{$d.claimID}</td>
    <td>{$d.senderName}{if $d.senderOrg != ''}, {$d.senderOrg}{/if}<br/>&lt;{$d.senderEmail}&gt;</td>
    <td>{$d.recipients}</td>
    <td>{$d.subject}</td>
    <td data-order="{$d.Bytes}">{$d.formattedBytes}</td>
    <td>{$d.createdDate|date_format:"%Y-%m-%d %H:%M:%S"}</td>
    <td>{$d.expiresDate|date_format:"%Y-%m-%d %H:%M:%S"}</td>
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
