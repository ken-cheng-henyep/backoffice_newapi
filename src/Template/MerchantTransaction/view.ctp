<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Merchant Balance Report') ?></h3>
    <div class="users form">
        <?= $this->Flash->render('auth') ?>
        <?= $this->Form->create(null, ['url' => ['controller' => 'MerchantTransaction', 'action' => 'view']]) ?>
        <ul class="fieldlist">
            <li>
                <label for="">Merchant</label>
                <?=  $this->Form->select('merchant',
                $account_lst,
                ['empty' => '(choose one)', 'required' => false, 'id'=>'merchantDropdown', 'default' =>$id]); ?>
            </li>
            <li>
                <label for="">Wallet</label>
                <input id="walletDropdown"/>
            </li>
            <li>
                <label for="">Transaction Date</label>
                <span>From </span><input id="start" name="start" value="<?= (empty($_REQUEST['start'])?date('Y/m/d',strtotime('-14 day')):$_REQUEST['start']) ?>"/>
                <span>To </span><input id="end" name="end" value="<?= (empty($_REQUEST['end'])?date('Y/m/d'):$_REQUEST['end']) ?>"/>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Search'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'updateGrid()']); ?>
                    <?= $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'onclick'=>'downloadExcel()', 'data-icon'=>""]); ?>
                </div>
            </li>
        </ul>

        <?= $this->Form->end() ?>
    </div>
    <div>&nbsp</div>
    <div id="currcy">Currency: <span id="symbol"></span></div>
    <div id="grid"></div>
    <div>&nbsp</div>
<?php
    if ($showUpdateBox)
            print $this->element('merchant_balance_update'); /*check Manager role*/
?>
</div>

<script>
$(document).ready(function() {
    function startChange() {
        var startDate = start.value(),
                endDate = end.value();

        if (startDate) {
            startDate = new Date(startDate);
            startDate.setDate(startDate.getDate());
            end.min(startDate);
        } else if (endDate) {
            start.max(new Date(endDate));
        } else {
            endDate = new Date();
            start.max(endDate);
            end.min(endDate);
        }
    }

    function endChange() {
        var endDate = end.value(),
                startDate = start.value();

        if (endDate) {
            endDate = new Date(endDate);
            endDate.setDate(endDate.getDate());
            start.max(endDate);
        } else if (startDate) {
            end.min(new Date(startDate));
        } else {
            endDate = new Date();
            start.max(endDate);
            end.min(endDate);
        }
    }

    var start = $("#start").kendoDatePicker({
        format: "yyyy/MM/dd",
        change: startChange
    }).data("kendoDatePicker");

    var end = $("#end").kendoDatePicker({
        format: "yyyy/MM/dd",
        change: endChange
    }).data("kendoDatePicker");

    start.max(end.value());
    end.min(start.value());

    $("#merchantDropdown").kendoDropDownList({
        change: function(e) {
            var value = this.value();
            // Use the value of the widget
            console.log('merchantDropdown change mid:'+ value);
            $('#walletDropdown').data("kendoDropDownList").dataSource.read({id:value});

            console.log('select 1st wallet');
            //$('#walletDropdown').data("kendoDropDownList").select(0);
            selectDD(-1);
            //$('#walletDropdown').data("kendoDropDownList").refresh();
            console.log('droplist ds read');

            updateGrid();
            //if (value==0 || value==null)
            $('#update_btn').attr('disabled', (value==0 || value==null));
        }
    });
    $("#merchantDropdown").data("kendoDropDownList").list.width(600)
    ;
    /*
     $(".kdbutton").kendoButton({
     spriteCssClass: "k-icon k-edit"
     });
     */
    var mid = $("#merchantDropdown").data("kendoDropDownList").value();
    kendo.init("#buttonContainer");

    console.log('mid:'+ mid);
    if (mid==0 || mid==null)
        $('#update_btn').attr('disabled', true);

    // Grid
    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: "<?=$json_url?>/"+mid,
            dataType: "json",
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                fields: {
                    create_time: { type: "date" }
                }
            }
        },
        pageSize: 10,
        sortable: true,
        serverPaging: false,
        serverSorting: false,
        sort: { field: "create_time", dir: "asc" }
    });

    $("#grid").kendoGrid({
        columns: [
            {field: "merchant_name",title: "Merchant", width: 300},
            {field: "create_time",title: "Date", format: "{0:yyyy-MM-dd HH:mm:ss}", width: 200},
            {field: "type_name",title: "Particulars", width: 160},
            {field: "amount",title: "Amount", format: "{0:n2}", width: 120, attributes:{class:"numeric"}},
            {field: "balance",title: "Balance", format: "{0:n2}", width: 120, attributes:{class:"numeric"}},
            //{field: "status",title: "Status", width: 150, sortable: false},
            //{field: "status_name",title: "Status", width: 150, sortable: false},
            {field: "username",title: "Operator", width: 150, sortable: true},
            {field: "remarks",title: "Remarks", width: 300, sortable: false, template: function(item) {
                // Batch <Batch ID> approved
                var text = '';
                if (item.remarks != null)
                    return item.remarks;

                return text;
                }
            },
        ],
        dataSource: dataSource,
        autoBind: false,
        height: 400,
        /*change: onGridChange, */
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        pageable: {
            refresh: true,
            pageSizes: [10, 20, 50, "All"],
            buttonCount: 2,
        },
        filterable: false,
        resizable: true,
        scrollable: true,
    });

    //select wallet
    var walletDs = new kendo.data.DataSource({
        transport: {
            read: {
                //url:"<?=$wallet_url?>/"+mid,
                url:"<?=$wallet_url?>",
                dataType: "json",
                //id: mid
            }
        },
        schema: {
            model: { id: "value" }
        },
        requestEnd: function(e) {
            var response = e.response;
            var dropdown = $('#walletDropdown').data("kendoDropDownList");
            console.log('ds len:'+response.length); // displays "77"
            //$('#walletDropdown').data("kendoDropDownList").value("<?=$wallet_id?>");
            //if (response.length>0)
            /*
            if (false)
            {
                console.log('select 1 now');
                dropdown.select(1);
                dropdown.trigger("change");
            }
            */
        }
    });

    $("#walletDropdown").kendoDropDownList({
        //value: "<?=$wallet_id?>" ,
        dataTextField: "text",
        dataValueField: "value",
        dataSource: walletDs,
        dataBound: function(e) {
            // handle the event
            //this.value("<?=$wallet_id?>");
            console.log('ddown bound val:'+ this.value());
            //updateGrid();
            /*
            updateCny();
            */
            if (this.value()>0) {
                selectDD("<?=$wallet_id?>");
            } else {
                console.log('no wallet val');
                selectDD(-1);
            }
        },
        change: function(e) {
            var value = this.value();
            console.log('walletDropdown change, wallet_id:'+ value+' mid:'+mid);
            // Use the value of the widget
            updateGrid();
            //$('#update_btn').attr('disabled', (value==0 || value==null));
            updateCny();
        }
    });

    var dropdown = $('#walletDropdown').data("kendoDropDownList");
    $('#walletDropdown').data("kendoDropDownList").dataSource.read({id:mid});
    //$('#walletDropdown').data("kendoDropDownList").value("<?=$wallet_id?>");
    //load data
    //dropdown.select(1);
    //updateGrid();
});

function updateGrid() {
    console.log('in updateGrid, wid='+$("#walletDropdown").data("kendoDropDownList").value());
    //update grid
    $('#grid').data("kendoGrid").dataSource.filter( [
        {field: "start", operator: "eq", value: $("#start").val()},
        {field: "end", operator: "lte", value: $("#end").val()},
        {field: "id", operator: "eq", value: $("#merchantDropdown").data("kendoDropDownList").value()},
        {field: "wallet_id", operator: "eq", value: $("#walletDropdown").data("kendoDropDownList").value()},
    ]);
    $('#grid').data("kendoGrid").dataSource.filter.logic = 'and';
    //todo : update currency
}

function updateCny() {
    var val = $('#walletDropdown').data("kendoDropDownList").value();
    console.log('updateCny, wid='+val);
    //console.log(typeof val);
    if (typeof val !== "undefined") {
        var item = $('#walletDropdown').data("kendoDropDownList").dataSource.get(val);
        if (typeof item !== "undefined") {
            console.log(item.currency);
            $('span#symbol').html(item.currency);
            $('input#symbol').val(item.currency);
        }
    }

}

function selectDD(idx) {
    //if idx<0, select 1st item
    var dropdown = $('#walletDropdown').data("kendoDropDownList");
    var val = dropdown.value();
    var found = false;

    console.log('selectDD, wid=' + idx);
    /*
    dropdown.items().forEach(function(item, index, array) {
        console.log(item, index);
    });
    */
    /*
    if (idx>0)
        idx = idx - 1;
    else
        idx = 0;
        */
    //$('#walletDropdown').data("kendoDropDownList").value("Remittance Balance");
    //dropdown.search("Remittance Balance");
    dropdown.select(function(dataItem) {
        //select same wallet ID
        found = (dataItem.value == idx);
        return found;
    });
    console.log('selectDD : set value ='+idx);
    if (! found) {
        console.log('selectDD : select 1st item');
        dropdown.select(0); //select first item
    }
    dropdown.trigger("change");
}

function downloadExcel() {
    console.log("downloadExcel now");
    $.get(
            '<?=$json_url?>',
            {"filter[filters]": [
                { field: "start", operator: "eq", value: $("#start").val()},
                {field: "end", operator: "lte", value: $("#end").val()},
                {field: "id", operator: "eq", value: $("#merchantDropdown").data("kendoDropDownList").value()},
                {field: "wallet_id", operator: "eq", value: $("#walletDropdown").data("kendoDropDownList").value()},
            ], "type":"excel"
            },
            function( data ) {
                console.log(data);
                if (data.status==1 && data.total>0) //success
                    $(location).attr('href', data.path);
            },
            'json'
    );
}
</script>
