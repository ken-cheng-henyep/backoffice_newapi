<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Blacklist Filter Setup') ?></h3>
    <div class="users form">
        <?= $this->Flash->render('auth') ?>
        <?= $this->Form->create(null, ['url' => ['controller' => 'RemittanceFilter', 'action' => 'blacklist']]) ?>
        <ul class="fieldlist">
            <li>
                <label for="">Merchant</label>
                <?=  $this->Form->select('merchant', $merchant_lst,
                ['empty' => 'All', 'required' => false, 'id'=>'merchantDropdown', 'default' =>null]); ?>
            </li>
            <li>
                <label for="">Type</label>
                <?=  $this->Form->select('type', ['id_number'=>'ID Card No.', 'account'=>'Account No.'],
                ['empty' => null, 'required' => true, 'id'=>'typeDropdown', 'default' =>'id_number']); ?>
            </li>
            <li>
                <label for="">Value</label>
                <!-- <input type="text" id="val" name="val" class="k-textbox" placeholder="" style="width: 220px;" /> -->
                <?= $this->Form->input('val',['type' => 'text','label'=>false,'id'=>'val', 'required' => true, 'value'=> '', 'placeholder'=>'', 'style'=>"width: 200px;" ]) ?>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Add'), ['type' => 'submit', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'']); ?>
                    <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel"]); ?>
                </div>
            </li>
        </ul>

        <?= $this->Form->end() ?>
    </div>
    <div>&nbsp</div>
    <h3><?= __('Current Filters') ?></h3>
    <div id="grid"></div>
    <div>&nbsp</div>
</div>

<script>
$(document).ready(function() {

    $("#merchantDropdown").kendoDropDownList({
        change: function(e) {
            var value = this.value();
            // Use the value of the widget
            //if (value==0 || value==null)
            //$('#update_btn').attr('disabled', (value==0 || value==null));
        }
    });
    $("#merchantDropdown").data("kendoDropDownList").list.width(600);

    $("#typeDropdown").kendoDropDownList();
    $("#typeDropdown").data("kendoDropDownList").list.width(200);

    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: "<?=$json_url?>",
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
            {field: "id",title: "Id", width: 40},
            {field: "merchant_name",title: "Merchant", width: 300},
            //{field: "type_name",title: "Type", width: 160},
            {field: "field_text",title: "Type", width: 160},
            {field: "field_val",title: "Value", width: 200},
            {field: "status_name",title: "Status", width: 150, sortable: false},
            //{field: "username",title: "Operator", width: 150, sortable: true},
            //{field: "remarks",title: "Remarks", sortable: false},
            //{field: "action",title: "Action", width: 180, sortable: false},
            {field: "action", title: "Action", width: 120, sortable: false
                , template: "<a class='${action_class}' href=\"javascript:void(0);\" onclick=\"setStatus('${id}','${action_val}');\">${action_txt}</a>&nbsp;&nbsp;&nbsp;" +
            "<a onclick=\"deleteFilter('${id}');\">Delete</a>"
                , headerTemplate:"Action" }
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
            pageSizes: [10, 20, 50, "all"],
            /*
             pageSizes: true,
             buttonCount: 2
             */
        },
        filterable: false,
    });

    //load data
    updateGrid();
});

function updateGrid() {
    //update grid
    console.log("updateGrid");
    $('#grid').data("kendoGrid").dataSource.filter([
    ]);
    $('#grid').data("kendoGrid").dataSource.filter.logic = 'and';
    //$('#grid').data('kendoGrid').dataSource.read();
}

function setStatus(id, val) {
    console.log("setStatus: "+id+","+ val );

    if (confirm("Are you sure you want to "+val+" filter #"+id+" ?")) {
        $.post("<?=$update_url?>", 'id=' + id + '&status=' + val, function (data) {
            console.log("return:" + data.status);
            if (data.status == 1) {
                //show updated total
                updateGrid();
                //location.reload();
                //$('#grid').data('kendoGrid').dataSource.read();
                return true;
            }
        }, 'json');

        console.log('end post');
    }
    return false;
}

function deleteFilter(id) {
    console.log("deleteFilter: "+id);
    if (confirm("Are you sure you want to delete filter #"+id+" ?")) {
        $.post("<?=$delete_url?>", 'id=' + id, function (data) {
            console.log("return:" + data.status);
            if (data.status == 1) {
                //show updated total
                updateGrid();
                //location.reload();
                //$('#grid').data('kendoGrid').dataSource.read();
                //$('#GridName').data('kendoGrid').refresh();
                return true;
            }
        }, 'json');

        console.log('end post');
    }
    return false;
}

</script>