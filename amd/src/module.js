define(['jquery','report_monitoring/highcharts', 'report_monitoring/select2'], function($,Chart){

  function init(){

        $(document).ready(function(){
          $('.select2').select2();

          $('.greport').each(function(){
            var report = $(this),
                tid = report.data('teacher');
              report.highcharts({
                  chart: {
                      type: 'bar'
                  },
                  xAxis: {
                      categories: data[tid].groups,
                      title: {
                          text: null
                      }
                  },
                  yAxis:{
                    min: 0,
                    max: 20,
                    allowDecimals: false,
                    tickInterval : 2
                  },
                  title: {
                      text: data[tid].title
                  },
                  plotOptions: {
                      bar: {
                          dataLabels: {
                              enabled: true
                          }
                      }
                  },
                  credits: {
                      enabled: false
                  },
                  series: data[tid].data
              });
          })
          data.done = true;
        });
        

  }
  return {
    init: init,
  }
});
