(function($){

  var options = {
    bg: '#2f2f2f',
  	target: document.getElementById('paperlit-progressbar'),
  	id: 'paperlit-progressbar'
  };

  var nanobar = new Nanobar( options );

  var themes_loaded = false;
  $(".nav-tab").click(function(){

    $(".nav-tab").removeClass('nav-tab-active');
    jQuery(this).addClass('nav-tab-active');

    var tab = $(this).data('tab');
    switch(tab) {
      case 'installed':
        $('#themes-remote').fadeOut('fast', function() {
          $('#themes-installed').fadeIn('fast');
        });
        break;
      default:
        break;
    }
  });



  $("#paperlit-theme-upload").change(function(){ $("#paperlit-theme-form").submit();});
  $(".paperlit-theme-delete").click(function(){ if(confirm(paperlit.delete_confirm)){
    nanobar.go( 10 );
    $parent = $(this).parent().parent('.theme');
    nanobar.go( 30 );
    $.post(ajaxurl, {
      'action':'paperlit_delete_theme',
      'theme_id': $parent.data('name')
    }, function(response) {
      if (response.success) {
        nanobar.go( 60 );
        $counter = parseInt($('#paperlit-theme-count').text()) - 1;
        $('#paperlit-theme-count').text( $counter );
        $parent.fadeOut();
        nanobar.go( 100 );
      } else {
        alert(paperlit.delete_failed);
      }
    });
  }});

  $("#paperlit-flush-themes-cache").click(function(){
    $.post(ajaxurl, {
      'action':'paperlit_flush_themes_cache'
    }, function(response) {
      if (response.success) {
        document.location.href = paperlit.flush_redirect_url;
      } else {
        alert(paperlit.flush_failed);
      }
    });
  });

  $("#paperlit-theme-add").on('click', function(e){
    e.stopPropagation();
    e.preventDefault();
    $("#paperlit-theme-upload").click();
  }).on('dragenter', function(e) {
    e.stopPropagation();
    e.preventDefault();
    //$('.add-new-theme').css('background-color', '#0074A2');
  }).on('dragover', function(e) {
    e.stopPropagation();
    e.preventDefault();
  }).on('drop', function(e) {
    //$('.add-new-theme').css('background-color', '#0074A2');
    e.preventDefault();
    var files = e.originalEvent.target.files || e.originalEvent.dataTransfer.files;
    uploadTheme(files[0]);
  });

  $(document).on('dragenter', function(e) {
    e.stopPropagation();
    e.preventDefault();
  }).on('dragover', function(e) {
    e.stopPropagation();
    e.preventDefault();
    //$('.add-new-theme').css('background-color', '#0074A2');
  }).on('drop', function(e) {
    e.stopPropagation();
    e.preventDefault();
  });

  function uploadTheme(file) {
    var formData = new FormData();
    formData.append('action', 'paperlit_upload_theme');
    formData.append('paperlit-theme-upload', file);

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: formData,
      cache: false,
      processData: false,
      contentType: false,
      success: function(data) {
        if( data.success ) {
          document.location.reload();
        } else {
          alert( paperlit.theme_upload_error );
        }
      }
    });
  }

  $('.show-code').click(function(){
    var index = $(this).data('index');
    $('.discount-code-' + index).slideToggle();
  });

  $('.paperlit-dismiss-notice').click(function(){
    var index = $(this).data('index');
    console.log(index);
    $.post(ajaxurl, {
      'action':'paperlit_dismiss_notice',
      'id'    : index,
    }, function(response) {
      if (response) {
        $('.discount-container-' + index).remove();
      }
    });
  });

})(jQuery);
