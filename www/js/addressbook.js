// Address book support code, including tooltips and delete buttons


// Is the tooltip above the autocomplete list visible?
var tooltipVisible = false;

// This one must be defined in the *.tpl file so it gets Smarty
// vars substituted in correctly.
// var addressbook = {$addressbook};
// var deleteText = '{t}Delete{/t}';

var addressnames = [];
var addressmails = [];
var fulladdressmails = [];
var fulladdressmap = [];

// Return the passed array with all elements matching value removed.
// Only works on simple lists, but that's all I need.
function arrayRemove(arr, value) {
  return arr.filter(function(el) {
    return el != value;
  });
}

// Called by user clicking on "X" to the right of an addressbook
// autocomplete suggestion.
function deleteAddressEntry (event) {
  var entry = $(this).attr("data-contact");
  var i = $(this).find('i');
  i.removeClass("fa-trash-alt").addClass("fa-spin fa-spinner");
  // Use Ajax to run deleteentry.php and pass it the entry name
  $.post("deleteentry.php",
         { delete: entry },
         function(reply, status) {
           if (reply.trim() == "done") {
             // Delete them locally only if remote deletion worked
             i.removeClass("fa-spin fa-spinner").addClass("fa-check");
             fulladdressmails = arrayRemove(fulladdressmails, entry);
             // Both of these needed to force refresh of drop-down
             $('#recipName').autocomplete("option", "source", fulladdressmails);
             $('#recipName').autocomplete("search");
           } else {
             // Remote deletion failed, so replace spinner with "!"
             i.removeClass("fa-spin fa-spinner").addClass("fa-exclamation");
           }
         });
  event.stopPropagation();
}

// This is so I can bind stuff to show and hide.
// Taken from
// https://viralpatel.net/blogs/jquery-trigger-custom-event-show-hide-element/
(function($) {
  $.each(['show', 'hide'], function(i, event) {
    var el = $.fn[event];
    $.fn[event] = function() {
      this.trigger(event);
      return el.apply(this, arguments);
    };
  });
  //$.event.special.destroy = {
  //  _default: function(event) {
  //    $(event.target).remove();
  //  }
  //};
})(jQuery);

// Immediately hide a jquery.balloon. Used in show and hide handlers.
// Need it as some data gets lost somewhere, so the real balloon code
// doesn't think $(this) is a balloon at all. Not sure why, but this
// works round it well enough.
function hideTooltips() {
  $(".tooltip").each(function(i) {
    $(this).stop(true, true).hide().data('active', false);
  });
  tooltipVisible = false;
}

//
// Setup all the address book functionality.
// This is called from the doc's ready() function.
//
function addAddressBook () {
  // Populate the address book structures
  $.each(addressbook,function(i,item){
    var fullmail = item['name']+' <'+item['email'] + '>';
    if ($.inArray(item['name'],addressnames)==-1)  addressnames.push(item['name']);
    if ($.inArray(item['email'],addressmails)==-1) addressmails.push(item['email']);
    if ($.inArray(fullmail,fulladdressmails)==-1)  fulladdressmails.push(fullmail);fulladdressmap[fullmail]=item;
  });

  // Autocomplete the recipEmail input box.
  $('#recipEmail').autocomplete({
    source: addressmails,
    appendTo: $('#recipEmail').parent()
  }).autocomplete('widget').css('text-align','left');

  // Autocomplete the recipName input box. This has delete buttons too.
  $('#recipName').autocomplete({
    source: fulladdressmails,
    appendTo: $('#recipName').parent(),
    select: function(e, ui) {
      $('#recipName').val(fulladdressmap[ui.item.label].name);
      $('#recipEmail').val(fulladdressmap[ui.item.label].email);
      return false;
    }
  }).data("ui-autocomplete")._renderItem = function(ul, item) {
    // How to render the row with the delete button on the right-hand end
    var li = $("<li/>").addClass("ac-row");
    var column1 = $("<div/>").addClass("ac-col1");
    var column2 = $("<div/>")
                  .addClass("ac-col2")
                  .addClass("deleteme")
                  .attr("data-contact", item.label)
                  .on("click", deleteAddressEntry);
    var innermost = $("<div/>")
                    .addClass("x-col")
                    .addClass("deleteme")
                    .append('<i class="fas fa-fw fa-trash-alt"></i>');
    column2.append(innermost);
    column1.append(document.createTextNode(item.label));
    li.append(column1).append(column2);
    return li.appendTo(ul);
  };

  // Autocomplete the multiple recipients box if it exists
  // #multipleRecipients doesn't always exist on the page
  if ( $('#multipleRecipients').length ) {
    $('#multipleRecipients').autocomplete({
      source: fulladdressmails,
      appendTo: $('#multipleRecipients').parent()
    }).autocomplete('widget').css('text-align','left');
  }

  // Add the tooltip to all the "X" buttons on the end of the lines
  // in the autocomplete lists for recipients.
  // As they are dynamic, do it via mouseenter and mouseleave.
  // Show the balloon above the first X button in the list when the
  // mouse enters any X button.
  // Hide it only when the mouse leaves the entire dropdown menu, as
  // I don't think I can do it when the mouse leaves *every* X button.
  $(document).on('mouseenter', '.ac-col2', function() {
    // This should show the balloon above the 1st X button in the list
    // Don't show multiple ones, only want one at a time!
    if (! tooltipVisible) {
      $(this).closest('ul').find('.ac-col2').first().showBalloon({
        position: "top",
        html: true,
        classname: "tooltip",
        css: { fontSize: '100%' },
        contents: deleteText,
        hideDuration: 0,
        showAnimation: function (d, c) { this.fadeIn(d, c); }
      });
      tooltipVisible = true;
    }
  });
  $(document).on('mouseleave', '.ui-autocomplete.ui-front', hideTooltips);
  // Hide the balloon every time the menu is shown or hidden.
  // This should pick up keypresses as well, as they involve calling
  // show().
  // The code required to enable this is taken from
  // https://viralpatel.net/blogs/jquery-trigger-custom-event-show-hide-element/
  $(document).on('show hide', '.ui-widget ul', hideTooltips);

}

