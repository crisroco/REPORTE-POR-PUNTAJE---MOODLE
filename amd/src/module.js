define(['jquery','block_sync/select2'], function($){

  function init(){

    $(document).ready(function(){
		$('.select2').select2();
	});

  }
  return {
    init: init,
  }
});

/*define(['jquery'], function($){

    function init(){
        $(document).ready(function(){
            $('.collapse').click(function(e){
                e.preventDefault();
                var target = $(this).attr('target');
                $(target).slideToggle();
            });
        });
    }
    


});*/