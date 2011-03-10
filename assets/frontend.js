String.prototype.nl2br = function() { return this.replace( /\r\n|\r|\n/mg, '<br />'); }
String.prototype.br2nl = function() { return this.replace( /\<br(\s*\/|)\>|\<BR\>/mg, "\r\n"); }

var iyc_visible = false;
var edit_summary = false;

jQuery(function(){
    var $ = jQuery;

    $("#iyc_box a.toggle").click(function(){
        iyc_visible = !iyc_visible;
        if(!iyc_visible) {
            $("#iyc_box").stop().animate({right: -400});
        } else {
            $("#iyc_box").stop().animate({right: 0});
        }
        return false;
    });

    $("#iyc_add_comment").click(function(){
        $("#iyc_commentform").slideToggle();
        return false;
    });

    $("#iyc_commentform form").submit(function(){
        var name = $("input[name=iyc_name]", this).val();
        var comment = $("textarea[name=iyc_comment]", this).val();

        var data = $(this).serialize();

        if(name != '' && comment != '')
        {
            // POST an AJAX-call
            $("#iyc_loader").show();
            $.post(window.location, data, function(response){
                $("#iyc_commentform textarea").val('');
                $("#iyc_commentform").slideUp();
                $("#iyc_loader").hide();
                $("#iyc_comments").prepend(response);
            });
        } else {
            alert('Both fields are required');
        }
        return false;
    });

    $("#iyc_edit_summary").click(function(){
        if(!edit_summary)
        {
            // var summary = $("p.summary").html().replace(/<br>/g, "\n");
            var summary = $("p.summary").html().br2nl();
            $("p.summary").replaceWith('<div id="iyc_edit"><textarea rows="5" cols="20" name="summary">' + summary + '</textarea><a href="#" id="iyc_save_changes">Save Changes</a></div>');
            $("#iyc_save_changes").click(function(){
                var summary = $("textarea[name=summary]").val();
                $.post(window.location, {iyc_summary: summary}, function(){
                    $("#iyc_edit_summary").click();
                });
                return false;
            });
            $("textarea[name=summary]").focus();
            edit_summary = true;
        } else {
            var summary = $("textarea[name=summary]").val();
            $("#iyc_edit").replaceWith('<p class="summary">' + summary.nl2br() + '</p>');
            edit_summary = false;
            replaceLineBreaks();
        }
        return false;
    });
    replaceLineBreaks();

    $(".iyc_delete_comment").click(function(){
        var id_comment = $(this).attr("rel");
        $(this).parent().parent().remove();
        $.post(window.location, {iyc_delete_comment: id_comment});
        return false;
    });

    $(window).resize(function(){
        var h = $(window).height() > $(document).height() ? $(window).height() : $(document).height();
        $("#iyc_box").height(h);
    }).resize();

    // Check if there is a hash-tag set:
    if(window.location.hash != '')
    {
        if(window.location.hash.match(/iyc_comment/))
        {
            $("#iyc_box a.toggle").click();
            id_comment = window.location.hash.split('iyc_comment:')[1];
            $("div.iyc_comment[rel=" + id_comment + "]").addClass("active");
        }
    }
});

function replaceLineBreaks()
{
    var text = jQuery("p.summary")[0].innerHTML;
    jQuery("p.summary").html(text.nl2br());
    
}

