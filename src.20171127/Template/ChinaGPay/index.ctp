<style>
div.k-loading-mask {
    z-index: 100; /* must be larger than the z-index:2 of #container */
}
</style>
<div class="form large-9 medium-8 columns content" id="main">
<fieldset>
    <legend><?= __('Please Enter Order ID to query') ?></legend>
    <?= $this->Form->input('OrderID', ['id'=>'orderid', 'label'=>'China GPay Order ID', 'placeholder'=>'123456,987654']) //, Seperated by comma (e.g. 123456,987654)?>
    <?= $this->Form->button('Update', ['type' => 'button', 'id'=>'search_btn', ]); ?>
</fieldset>
    <div id="progress">
    </div>
    <div id="output">
        <?= $this->Form->textarea('result', ['id'=>'result', 'readonly'=>'true']); ?>
    </div>
</div>
<script>
$('#search_btn').click(function(){
    var id =  $("#orderid").val();
    //$(this).attr("disabled", true);
    //$(this).data("kendoButton").enable(false);
    if (id!='')
        sendQuery(this, id);
    //$(this).data("kendoButton").enable(true);
});

function sendQuery(btn, id) {
    console.log("sendQuery()");
    $("#result").val('');

    var url = '<?=$this->Url->build(["controller" => "ChinaGPay","action" => "json"])?>';
    console.log("id:"+id+" url:"+url);

    if (id!='') {
        var element = $(document.body);
        //var element = $("#progress");
        kendo.ui.progress(element, true);
        $(btn).data("kendoButton").enable(false);

        var p = $.post(url, 'id=' + id, function (data) {

            console.log("status:" + data.status);
            if (data.status == 0) {
                //location.reload();
                console.log(data.data);
                var items = data.data.map(function (item) {
                    return item.id + ': Result [' + item.transaction + "\r\n" + item.return + ']';
                });
                var text='';
                $.each(items, function(index, value) {
                    text+= value + "\r\n";
                });
                $("#result").val(text);

            }
            kendo.ui.progress(element, false);
            $(btn).data("kendoButton").enable(true);
            console.log('end post');
        }, 'json');
        setTimeout(function(){ p.abort(); }, 10000);    //10s
    }
    //$('#search_btn').attr('disabled', false);
}

$(document).ready(function() {
    $("#search_btn").kendoButton();

});
</script>