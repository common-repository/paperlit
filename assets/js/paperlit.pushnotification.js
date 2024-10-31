jQuery(function(){
  jQuery('.tab').click(function(e){
    var $this = jQuery(this);
    jQuery('.tab').removeClass('activeTab');
    jQuery('.paperlit-prp-panel').removeClass('active');
    $this.addClass('activeTab');
    jQuery('.paperlit-prp-panel:eq('+$this.index()+')').addClass('active');
  });
  jQuery('#paperlit-btn-continue').click(function(){
    jQuery('.tab:eq(1)').click();
  });
  jQuery('.paperlit-prp-type').change(function(){
    var $this = jQuery(this);
    if ( $this.is(':checked') && $this.val() == 'download' ) {
      jQuery('#paperlit-edition-d').slideDown();
    }
    else {
      jQuery('#paperlit-edition-d').slideUp();
    }
  });
  jQuery('.paperlit-prp-time').change(function(){
    var $this = jQuery(this);
    if ( $this.is(':checked') && $this.val() == 'later' ) {
      jQuery('#paperlit-edition-t').slideDown();
    }
    else {
      jQuery('#paperlit-edition-t').slideUp();
    }
  });

  jQuery('#paperlit-prp-edition-0').click(function(){jQuery('#paperlit-prp-edition-s').prop('disabled', 'disabled');});
  jQuery('#paperlit-prp-edition-1').click(function(){jQuery('#paperlit-prp-edition-s').prop('disabled', false);});
  jQuery('input[name="paperlit_push[editorial_project]"]').change(function(){
    var $this = jQuery(this);
    if ( $this.is(':checked')) {
      jQuery('#paperlit-prp-edition-s').load(ajaxurl, {'action' : 'paperlit_push_get_editions_list', 'eproject_slug' : jQuery(this).val() });
    }
    jQuery('#paperlit-prp-edition-0').click();
  }).change();
  jQuery('#paperlit-push-form').submit(function(e){
    e.preventDefault();
    var $this = jQuery(this);
    $clog = jQuery('#paperlit-push-console');
    jQuery('.tab:eq(2)').click();
    jQuery.ajax({
      url: ajaxurl,
      dataType: 'json',
      data: $this.serialize() + '&action=paperlit_send_push_notification',
      method: 'post',
      beforeSend: function() {
        $clog.empty().append('Sending...<br>');
      }
    }).done(function(data) {
      if ( data.success ) {
        $clog.append( '<span class="cs-success">Sending success.</span><br>' + data.data );
      }
      else {
        $clog.append( '<span class="cs-error">Sending failed.<br>' + data.data + '</span>' );
      }
    });
  });

  jQuery('#paperlit-prp-rp-time').datetimepicker({
    format: 'Y-m-d H:i:s',
    lang: 'en',
    minDate: 0,
    minTime: moment().add(1, 'hours').format('HH:00'),
    maxDate: moment().add(14, 'days').format('YYYY/MM/DD'),
    inline: true,
    yearStart: moment().format('YYYY'),
    yearEnd: moment().add(14, 'days').format('YYYY'),
    scrollMonth: false,
    onSelectDate: function(c) {
      var d = moment();
      var t = moment().add(1, 'hours').format('HH:00');
      this.setOptions({
        minTime: ( c.dateFormat('Y/m/d') != d.format('YYYY/MM/DD') ? false : t )
      });
    },
    onChangeDateTime:function(dp,$input){
      $input.val( moment(dp).tz("Europe/Berlin").format('YYYY-MM-DD HH:mm') );
    }
  });
});
