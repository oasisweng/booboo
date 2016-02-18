
var path = window.location.href.split('?')[0];
//get endAt date from database
$.ajax({
  url: path+"/columns/endAt",
  async: true,
  dataType: 'json',
  success: function (result) {
    var endTime = new Date(result.auction.endAt);
    $('#timeleft').countdown(endTime, {elapse: true}).on('update.countdown', function(event) {
    var $this = $(this);
    if (event.elapsed) {
      $this.html(event.strftime('Auction has ended.'));
      //reload this page to
      location.reload();
    } else {
      $this.html(event.strftime('Time Left : <span>%H hours : %M mins :%S seconds</span>'));
    }
    });
  }
});
