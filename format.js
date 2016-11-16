$(function(){

        $(document).ready(function(){
            $('.collapsable').click(function(e){
                e.preventDefault();
                var target = $(this).attr('target');
                $(target).slideToggle();
            });
        }); 
});