jQuery(document).ready( function($){

    if (biiButtonExists())
    {
        var modal = document.createElement('div');
        modal.innerHTML = ''+
            '<div style=\"display:none\" id="dialog" title="Please accept the Universal Terms and Conditions">'+
            '<p>In relation to my purchase of On-Site Services, I have read, understand and accept the '+
            '<a target="_BLANK" style=" text-decoration: underline; color:darkblue" href="https://www.buyitinstalled.com/utc">Buy It Installed\'s Universal Terms and Conditions</a></p>'+
            '</div>';

        $('body').after(modal);

        // FUNCTIONALITY TO HANDLE THE CLICK OF THE BUY INSTALLED BUTTON
        var biiButton = document.querySelector('[name="bii"]');
        biiButton.addEventListener('click', function(e){return addUTC(e);});

    }

    // does the biiButton exist on page?
    function biiButtonExists()
    {
        return (document.querySelector('[name="bii"]')!==null);
    }

    function addUTC(e)
    {
        e.preventDefault();
        $( "#dialog" ).dialog({
            modal:true,
            width:600,
            buttons:
                {
                    "I Accept": function() {
                        // the button value's needs to be re-added to the form submit since it is ignored after preventSubmit
                        var serviceItemProductId = $('#bii').val();
                        var input = $("<input>")
                                .attr("name", "bii")
                                .attr("type", "hidden").val(serviceItemProductId);
                        $( ".cart" ).append($(input));
                        $( '[name="add-to-cart"]' ).click();
                        $( this ).dialog( "close" );
                    },
                    "Close": function() {
                        $( this ).dialog( "close" );
                    }
                }
        });
    }

});