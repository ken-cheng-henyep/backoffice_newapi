<h3><?= __('Merchant Wallet') ?></h3>
<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>
    <?= $this->Form->create(null, ['id'=>"mform",'url' => ['controller' => 'Merchants', 'action' => 'add']]) ?>
    <ul class="fieldlist">
    <li>
        <label for="">Master Merchant:</label>
        <?=  $this->Form->select('master_merchant',
        $master_lst,
        ['empty' => '(choose one)', 'required' => true, 'id'=>'merchantDropdown', 'data-fieldname'=>'Master Merchant']); ?>
    </li>
    </ul>
    <?= $this->Form->end() ?>

    <h3><?= __('Wallet List') ?></h3>
    <div id="grid"></div>
    <div>
        <?php
                /*
                $action = 'addGroup';
                $url = $this->Url->build([ "action" => $action,]);
                print $this->Form->button('Add', ['type' => 'button', 'id'=>'add_btn', 'onclick'=> "window.location.href = '$url';"]);
                */
                ?>
    </div>
</div>

<script>
$(document).ready(function() {
    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: {
                url:'<?=$json_url?>',
                dataType: "json",
            },
            create: {
                url: '<?=$update_url?>',
                type: "POST"
            },
            update: {
                url: '<?=$update_url?>',
                type: "POST"
            },
        },
        success: function (data) {
            // console.log('success');
        },
        change: function (data) {
            //console.log('change');
        },
        requestEnd: function(e) {
            var response = e.response;
            var type = e.type;
            //console.log("requestEnd:"+type); // displays "read"
            //console.log(response);
            if (type=='update') {
                this.read();
                if (response.status==-1 && typeof response.msg !== 'undefined')
                    popup(response.msg);
            }
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                id: "id",
                fields: {
                    id: {
                        editable: true,
                        nullable: false,
                        validation: { //set validation rules
                            required: true
                        },
                        //type: "number"
                    },
                    name: {
                        validation: { //set validation rules
                            required: true
                        }
                    },
                    statusname: {editable: false,},
                    //create_time: { type: "date" },
                    //content_time: { type: "date" },
                }
            }
        },
        pageSize: 50,
        sortable: true,
        serverPaging: false,
        serverSorting: false,
        //sort: { field: "id", dir: "desc" }
    });

    //wallet list
    $("#grid").kendoGrid({
        columns: [
            {field: "id",title: "ID", width: 50, attributes:{}},
            {field: "name",title: "Name", width: 120, },
            {
                field: "currency",title: "Currency", width: 80,
                //template: "<a class='' href=\"javascript:void(0);\" data-id='${id}' onclick=\"update('${name}','${id}','${statusname}');\">${statusname}</a>"
            },
        ],
// data-id=${id}, data-bid=${batch_id}
        dataSource: dataSource,
        toolbar: ["create", "save", "cancel", "excel"],
        editable: { //disables the deletion functionality
            update: true,
            destroy: false
        },
        edit: function(e) {
            if (!e.model.isNew()) {
                // Disable the editor of the "id" column when editing data items
                e.container.find("input[name=id]").attr("readonly", true);
                /*
                var numeric = e.container.find("input[name=id]").data("kendoNumericTextBox");
                numeric.enable(false);
                */
            }
        },
        save: function(e) {
            console.log('after saved');
            //$.dataSource.read();
        },
        excelExport: function(e) {
            console.log('excelExport');
            //e.workbook.fileName = "MasterMerchantList.xlsx";
            e.preventDefault();
            window.location.href = "<?=$dl_url?>";
        },
        height: 600,
        /*change: onGridChange, */
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        pageable: {
            refresh: true,
            //pageSizes: true,
            pageSizes: [50, 100, "All"],
            buttonCount: 2
        },
        filterable: false,
        resizable: true,
        //scrollable: false,
    });

    //error dialog
    $("#errdialog").kendoDialog({
        width: 250,
        title: "Error",
        closable: false,
        visible: false,
        actions: [
            { text: 'OK', primary: true }
        ]
    }).data("kendoDialog");
});
//end ready

function popup(msg) {
    var dialog = $("#errdialog").data("kendoDialog");
    dialog.content(msg);
    dialog.open();
}
function update(name, id, cstatus) {
    var url = '<?=$update_status_url?>';
    if (cstatus.toLowerCase()=='disabled') {
        var status = 1;
        var statustext = 'enable';
    } else {
        var status = 0;
        var statustext = 'disable';
    }
    console.log("update("+id+", "+status+") url:"+url);

    if (confirm("Are you sure you want to "+statustext+" "+name+" ?")) {
        $.post(url, 'id=' + id + '&status=' + status, function (data) {
            console.log("return:" + data.status);
            if (data.status == 1) {
                //success
                $('#grid').data('kendoGrid').dataSource.read();
                //$('#GridName').data('kendoGrid').refresh();
                return true;
            }
        }, 'json');

        console.log('end post');
    }
    return false;
}
</script>
<style>
#grid > .k-grid-header > div > table,
#grid > .k-grid-content > table
{
    width: 100% !important;
}
</style>