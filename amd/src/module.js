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