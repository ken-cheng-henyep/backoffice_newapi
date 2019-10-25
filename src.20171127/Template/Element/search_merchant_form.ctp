<div class="search-input">
    <?= $this->Flash->render('auth') ?>
    <?= $this->Form->create(null, ['url' => ['controller' => 'Merchants', 'action' => 'index'], 'id'=>'form1']) ?>
    <ul class="fieldlist">
        <li>
            <span class="datebox-1">
            <label for="">Master Merchant:</label>
            <?=  $this->Form->select('master_merchant',
            $master_lst,
            ['empty' => 'All', 'required' => false, 'id'=>'dd-merchant', 'data-fieldname'=>'Master Merchant']); ?>
            </span>
        </li>
        <li>
            <span class="datebox-1"><label for="">Merchant Name</label><input id="name" name="name" value=""/></span>
            <span class="inputbox-2"><label for="">Merchant ID</label><input id="id" name="id" value=""/></span>
        </li>
        <li>
            <span class="datebox-1"><label for="">Enabled</label><?=$this->Form->select(
            'enabled',
            [1=>'Y', 0=>'N'],
            ['empty' => 'All', 'required' => false, 'id'=>'dd-enabled']); ?>
            </span>
            <span class="inputbox-2"><label for="">Settlement Currency</label><?=$this->Form->select(
            'settle_currency',
            ['USD'=>'USD', 'HKD'=>'HKD', 'EUR'=>'EUR', 'GBP'=>'GBP'],
            ['empty' => 'All', 'required' => false, 'id'=>'dd-settle-currency']); ?>
            </span>
        </li>
        <li>
            <span class="datebox-1"><label for="">FX Package</label><?=$this->Form->select(
            'settle_option',
            [1=>'Day of transaction', 2=>'Day of settlement', 3=>'No FX conversion'],
            ['empty' => 'All', 'required' => false, 'id'=>'dd-settle-option']); ?>
            </span>
            <span class="inputbox-2"><label for="">FX Rate Symbol</label><?=$this->Form->select(
            'settle_rate_symbol',
                ["CNYUSD_S1"=>"CNYUSD_S1",
                "CNYUSD_S2"=>"CNYUSD_S2",
                "CNYUSD_S3"=>"CNYUSD_S3",
                "CNYUSD_S4"=>"CNYUSD_S4",
                "CNYHKD_S1"=>"CNYHKD_S1",
                "CNYHKD_S2"=>"CNYHKD_S1"],
            ['empty' => 'All', 'required' => false, 'id'=>'dd-settle-rate-symbol']); ?>
            </span>
        </li>
        <li>
            <span class="datebox-1">
            <label for="">Local Remittance Enabled</label>
            <?=  $this->Form->select('local_remittance_enabled',
            [1=>'Enabled', 0=>'Disabled'],
            ['empty' => 'All', 'required' => false, 'id'=>'dd-local-remittance-enabled']); ?>
            </span>
            <span class="inputbox-2">
            <label for="">Pre-authorized Enabled</label>
            <?=  $this->Form->select('remittance_preauthorized',
            [1=>'Enabled', 0=>'Disabled'],
            ['empty' => 'All', 'required' => false, 'id'=>'dd-remittance-preauthorized']); ?>
            </span>
        </li>
        <li>
            <span class="datebox-1">
            <label for="">Remittance API Enabled</label>
            <?=  $this->Form->select('remittance_api_enabled',
            [1=>'Enabled', 0=>'Disabled'],
            ['empty' => 'All', 'required' => false, 'id'=>'dd-remittance-api-enabled']); ?>
            </span>
        </li>

        <div id="buttonContainer">
            <?= $this->Form->button(__('Search'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'updateGrid()']); ?>
            <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel", 'onclick'=>"$('form')[0].reset()"]); ?>
            <?= $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'id'=>'dl-excel', 'onclick'=>'downloadExcel()']); ?>
        </div>
    </ul>
    <?= $this->Form->end() ?>
</div>
<script>

var filters = null;

$(document).ready(function() {
    /*
    $("input[id|='dd']").kendoDropDownList();
    $("input[id|='dd']").data("kendoDropDownList").list.width(200);
    */
    $("#dd-merchant").kendoDropDownList();
    $("#dd-merchant").data("kendoDropDownList").list.width(400);

    $('input').keypress(function (e) {
        //console.log("keypress");
        if (e.which == 13) {    //enter
            //$("#form1").submit();
            updateGrid();
            e.preventDefault();
        }
    });

});
//ready
function updateGrid() {
    console.log("updateGrid now");
    filters = getFilters();
    console.log(filters);
    //update grid
    $('#grid').data("kendoGrid").dataSource.filter(filters);
    /*
    $('#grid').data("kendoGrid").dataSource.filter([
        {field: "master", operator: "eq", value: $("#dd-merchant").data("kendoDropDownList").value()},
        {field: "name", operator: "eq", value: $("#name").val()},
        {field: "id", operator: "eq", value: $("#id").val()},
        {field: "enabled", operator: "eq", value: $("#dd-enabled").val()},
        {field: "settle_currency", operator: "eq", value: $("#dd-settle-currency").val()},
        {field: "settle_option", operator: "eq", value: $("#dd-settle-option").val()},
        {field: "settle_rate_symbol", operator: "eq", value: $("#dd-settle-rate-symbol").val()},
        {field: "local_remittance_enabled", operator: "eq", value: $("#dd-local-remittance-enabled").val()},
        {field: "remittance_preauthorized", operator: "eq", value: $("#dd-remittance-preauthorized").val()},
        {field: "remittance_api_enabled", operator: "eq", value: $("#dd-remittance-api-enabled").val()},
    ]);
    */
    //$('#grid').data("kendoGrid").dataSource.filter.logic = 'or';
}

function downloadExcel() {
    console.log("downloadExcel now");
    filters = getFilters();
    console.log(filters);

    $.get(
        '<?=$json_url?>',
        {"filter[filters]": filters
            //{field: "merchant", operator: "eq", value: $("#merchantDropdown").val()},
            , "type":"excel"
        },
        function( data ) {
            console.log(data);
            if (data.status==1 && data.total>0) //success
                $(location).attr('href', data.path);
        },
        'json'
    );
}

function getFilters() {
    return [
        {field: "master", operator: "eq", value: $("#dd-merchant").data("kendoDropDownList").value()},
        {field: "name", operator: "eq", value: $("#name").val()},
        {field: "id", operator: "eq", value: $("#id").val()},
        {field: "enabled", operator: "eq", value: $("#dd-enabled").val()},
        {field: "settle_currency", operator: "eq", value: $("#dd-settle-currency").val()},
        {field: "settle_option", operator: "eq", value: $("#dd-settle-option").val()},
        {field: "settle_rate_symbol", operator: "eq", value: $("#dd-settle-rate-symbol").val()},
        {field: "local_remittance_enabled", operator: "eq", value: $("#dd-local-remittance-enabled").val()},
        {field: "remittance_preauthorized", operator: "eq", value: $("#dd-remittance-preauthorized").val()},
        {field: "remittance_api_enabled", operator: "eq", value: $("#dd-remittance-api-enabled").val()},
    ];
}
</script>