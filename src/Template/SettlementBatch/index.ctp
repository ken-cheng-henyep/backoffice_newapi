<div class="form search-input">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => ['action' => 'fetchMerchantUnsettled'] , 'name' => 'fetchMerchantUnsettled']) ?>
    <fieldset>
        <legend><?= __('New Batch - Select transaction') ?></legend>
        <p>Unsettled transactions summary:</p>
        

        <div id="grid-merchant"></div>
        <div class="clearfix"></div>
    <div class="btn-wrap">
<?= $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'left btn-download k-button']); ?>
<?= $this->Form->button(__('Refresh'), ['type' => 'button', 'class'=>'left btn-refresh k-button']); ?>
</div>
    </fieldset>
    <div>
    </div>

<?= $this->Form->end() ?>

<script>
$(function(){

    var columns = [{
        field: "name",
        title: "Merchant",
        width: 320,
            attributes:{style:"vertical-align:top;"}
    }, {
        field: "s_min_date",
        title: "From",
        width: 120, 
        template: '#= kendo.toString(kendo.parseDate(s_min_date), "yyyy/MM/dd")#',
            attributes:{style:"vertical-align:top;"}

    }, {
        field: "s_max_date",
        title: "To",
        width: 120,
        template: '#= kendo.toString(kendo.parseDate(s_max_date), "yyyy/MM/dd")#',
            attributes:{style:"vertical-align:top;"}

    }, {
        field: "s_currency",
        title: "Currency",
        width: 80,
        encoded: false,
            attributes:{style:"vertical-align:top;"}

    }, {
        field: "s_amount",
        title: "Amount",
        width: 140,
        encoded: false,
            attributes:{style:"vertical-align:top;text-align:right;"}

    }, {
        field: "s_count",
        title: "Count",
        width: 100,
        encoded: false,
            attributes:{style:"vertical-align:top;text-align:right;"}
    }, {
        field: "action",
        title: "Actions",
        width: 140,
        sortable: false,
        filterable: false,
            attributes:{style:"vertical-align:top;"},
        template: "<a class=\"btn-create-batch\" href=\"javascript:void(0);\" data-id=\"${id}\" data-url=\"${action_url}\">Create</a>"
    }, {}];

    var dataSource = new kendo.data.DataSource({
        serverFiltering: false,
        transport: {
            read: {
                url:'<?=$this->Url->build([ "action" => "fetchMerchantUnsettled"])?>',
                dataType: "json",
            },  
        },
        change: function (data) {
            console.log(arguments);
            //console.log('change');
            /*
            console.log('all_log_set:'+data.items[0].all_log_set);
            $('#log_ready').val(data.items[0].all_log_set);
            onTBChange();
            */
        },
        error: function (e) {
            alert('Sorry, server does not response valid result.\r\n\r\nPlease check the network connection or change the date range into smaller');
            console.log(e);
            //console.log('change');
            /*
            console.log('all_log_set:'+data.items[0].all_log_set);
            $('#log_ready').val(data.items[0].all_log_set);
            onTBChange();
            */
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                id: 'merchantgroup_id',
                fields: {
                    s_min_date: { type: "date", format: 'Y-m-d' },
                    s_max_date: { type: "date", format: 'Y-m-d' },
                    amount: {type: "number"},
                    count: {type: "number"}
                }
            }
        },
        pageSize: 25,
        sortable: true,
        serverPaging: false,
        serverSorting: false,
        sort: { field: "name", dir: "asc" }
    });

    $("#grid-merchant").kendoGrid({
        columns: columns,
        dataSource: dataSource,
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        height:600,
        filterable:false,
        pageable: {
            refresh: true,
            //pageSizes: true,
            pageSizes: [10, 25, 50, 100, "All"],
            buttonCount: 4
        },
        resizable: true,
        //scrollable: false,
    });

    $('body')
    .on('click', '.btn-create-batch', function(e){
        e.preventDefault();
        var data = $(this).data();

        // For fx package 2, need to verify the settlement rate before going next step.
        if(data.fx_package == '2'){
            $.getJSON('<?= $this->Url->build(['controller'=>'SettlementRate','action'=>'isUpdatedToday'])?>', function(rst){
                if(rst.today_updated){
                    location.href = data.url;
                }else{
                    alert('The Settlement FX Rate is outdated, please update the rate before continue.');
                }
            })
        }else{
            location.href = data.url;
        }
    })
    .on('click', '.btn-download', function(e){
        e.preventDefault();
        var data = $(this).data();

        $.post ( '<?= $this->Url->build(['action'=>'downloadMerchantUnsettled', ])?>', {}, function(rst){
            if(rst.status == 'done'){
                if(rst.url){
                    window.location.href = rst.url;
                }
            }
        },'json').error(function(){
            alert('Server cannot handle your request. Please try again later.');
        });

    })
    .on('click', '.btn-refresh', function(e){
        e.preventDefault();
        if(!confirm('It may take some time to recalculate the Unsettle Transaction Sammry. Click Confirm to proceed.')) return;

        $.getJSON('<?=$this->Url->build(['action'=>'refreshMerchantsUnsettled'])?>', function(rst){
            if(rst && rst.status == 'done'){
                dataSource.read(1);
            }
        }).error(function(){
            alert('Sorry, we cannot refresh your page. Please try again later.');
        })
        // dataSource.read(1);
    })

    $('body')
    .on('click','.btn-reload-from-db', function(e){
        e.preventDefault();

        if(!confirm('The modifications in the current session will be lost.'))return;
    })
    .on('click','.btn-submit', function(e){
        e.preventDefault();

        if(!confirm('The Settlement Amount will be deducted from the merchant balances as Settlement.'))return;
    })
})
</script>
<style>


@import url(<?=$this->Url->css('wc-extra')?>);
</style>