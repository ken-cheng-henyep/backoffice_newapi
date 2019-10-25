$(document).ready(function() {
    window.batch_id = $("#batch_id").text();

    var target_id = $("#target_id").val();

    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: {
                url:"json-"+batch_id+"/",
                dataType: "json",

            },


            /*
             function (e) {
             // on success
             e.success(sampleData);
             // on failure
             //e.error("XHR response", "status code", "error message");
             },
             */

        },
        success: function (data) {
            console.log('success');
        },
        change: function (data) {
            //console.log(data);
            console.log('all_log_set:'+data.items[0].all_log_set);
            $('#log_ready').val(data.items[0].all_log_set);
            onTBChange();
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                fields: {
                    upload_time: { type: "date" },
                    convert_amount: { type: "number" }
                }
            }
        },
        pageSize: 25,
        sortable: true,
        serverPaging: false,
        serverSorting: false,
        sort: { field: "id", dir: "asc" }
    });

    $("#grid").kendoGrid({
        toolbar: ["pdf"],
        pdf: {
            fileName: "remittance_batch.pdf",
            allPages: true,
            avoidLinks: true,
            paperSize: "A4",
            margin: { top: "2cm", left: "1cm", right: "1cm", bottom: "1cm" },
            landscape: true,
            repeatHeaders: true,
            template: $("#page-template").html(),
            scale: 0.6
        },
        columns: [{field: "index",title: "Id", width: 50, attributes:{id:"row-batch-id"}},
            {field: "account",title: "Account No.", width: 200, attributes:{}},
            {field: "beneficiary_name",title: "Name", width: 60},
            {field: "bank_name",title: "Bank Name", width: 120},
            {field: "bank_branch",title: "Bank Branch", width: 120},
            {field: "province",title: "Province", width: 80},
            {field: "city",title: "City", width: 80},
            {field: "id_number",title: "ID Card", width: 180},
            {field: "amount",title: "CNY", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
            //{field: "convert_amount",title: "USD", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "non_cny",title: "Currency", width: 60},
            {field: "convert_amount",title: "Converted Amount", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "convert_rate",title: "Rate", format: "{0:n4}", width: 80, attributes:{class:"numeric"}},
            {field: "tx_status_name",title: "Status", width: 80},
            {field: "merchant_ref",title: "Ref.", width: 100},
            /*{field: "api_log_intid",title: "API Id", width: 100},*/
            {field: "blocked",title: "Remarks", width: 120},
            {field: "flagged",title: "Flagged", width: 120},
            {
                field: "api_log_intid", title: "API Id", width: 100, sortable: false
                , template: function(item) {
                    //console.log(item);
                //return "<strong>" + kendo.htmlEncode(dataItem.name) + "</strong>";
                    if (item.api_log_intid !=null)
                        return "<a class='' href=\"javascript:void(0);\" onclick=\"showApiLog('"+item.batch_id+"','"+item.id+"');\">"+item.api_log_intid+"</a>";
                    return '';
                /*
                "<a class='' href=\"javascript:void(0);\" data-id='${id}' data-bid='${batch_id}' onclick=\"showApiLog('${batch_id}','${id}');\">${api_log_intid}</a>"
                 */
                }
            },
            {field: "action", title: "Actions", width: 100, sortable: false
               , template:"<a class='${action_class}' href=\"javascript:void(0);\" data-id='${id}' data-bid='${batch_id}' onclick=\"updateLogStatus('${batch_id}','${id}','${action}');\">${action_txt}</a>"
                , headerTemplate:"Actions<br/><a class='act-all-ok' href=\"javascript:void(0);\" onclick=\"updateLogStatus('','all','OK');\">Set All OK</a>" },
            {field: "admin_action", title: "Actions", width: 100, sortable: false
                , template:"<a class='${action_class}' href=\"javascript:void(0);\" data-id='${id}' data-bid='${batch_id}' onclick=\"updateLogStatus('${batch_id}','${id}','${action}','${action_val}');\">${action_txt}</a>"
                , headerTemplate:"Actions" }
        ],
// javascript:void(0);
// data-id=${id}, data-bid=${batch_id}
        dataSource: dataSource,
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
            pageSizes: [10, 25, 50, "All"],
            buttonCount: 2
        },
        filterable: false,
        resizable: true,
        scrollable: true,
    });

    $('#quote-rate').kendoNumericTextBox({
        value: 0,
        min: 0,
        max: 999,
        step: 0.0001,
        format: "n4",
        decimals: 4,
        change: onTBChange,
        spin: onTBChange
    });
    $('#complete-rate').kendoNumericTextBox({
        value: 0,
        min: 0,
        max: 999,
        step: 0.0001,
        format: "n4",
        decimals: 4,
        change: onTBChange,
        spin: onTBChange
    });

    var targetDs = new kendo.data.DataSource({
        transport: {
            read: {
                url:"target-json",
                dataType: "json"
            }
        },
        schema: {
            model: { id: "value" }
        }
    });

    $("#target").kendoDropDownList({
        value: target_id, //"<?=$remittanceBatch[0]['target']?>",
        dataTextField: "text",
        dataValueField: "value",
        dataSource: targetDs,
        /*
        dataSource: [
            { text: "(choose one)", value: "" },
            { text: "Payment Asia Excel", value: "1" },
            { text: "ChinaGPay Excel", value: "2" },
            { text: "GHT Excel", value: "6" },
            //[3=>'ChinaGPay API', 7=>'GHT API'];
        ],
        */
        change: onTBChange
    });

    var status = $('#status_name').val().toLowerCase();
    var log_ready = $('#log_ready').val();
    var quote_rate = $('#quote_rate').val();
    var complete_rate = $('#complete_rate').val();
    var target_data = $("#target").data("kendoDropDownList");
    var target_name = $('#target_name').val();
    var is_api = $.inArray( target_data.value(), ['3','5','7']);

    if ($('#admin_action').val() == "1")
        $('#grid').data("kendoGrid").showColumn("admin_action");
    else
        $('#grid').data("kendoGrid").hideColumn("admin_action");

    console.log("status:"+status+" log_ready:"+log_ready);
    if (status=='processing') {
        //$('#quote-rate').prop('readonly', true);
        $("#quote-rate").data("kendoNumericTextBox").value(quote_rate);
        $("#quote-rate").data("kendoNumericTextBox").readonly(true);
        //$('#complete-rate').prop('readonly', false);
        $("#complete-rate").data("kendoNumericTextBox").readonly(false);
        $("#complete-rate").data("kendoNumericTextBox").wrapper.show();

        //$('#target').attr('disabled', true);
        $("#target").data("kendoDropDownList").enable(false);
        $("#target").closest(".k-widget").hide();
        $("#target_txt").text(target_name);

        $('#ap_button').hide();
        //check target
        $('#ex_button').attr('disabled', false);
        $('#de_button').hide();
        //$('.actions').show();
        $('#grid').data("kendoGrid").showColumn("action");
        //if (log_ready)
        $('#cp_button').show();
        $('#cp_button').attr('disabled', (!readyToComplete()));
        $('#rp_button').show();

        console.log("target is_api:"+ is_api);
        //process by API
        if (is_api > -1) {
            $("#complete-rate").data("kendoNumericTextBox").wrapper.hide();
            $('#ex_button').hide();
            $('#cp_button').hide();
            $('#rp_button').hide();
            $('#msg').html("Remittance API is processing ...");
            $('#grid').data("kendoGrid").hideColumn("action");
        } else if ($('#admin_role').val() == "1") {
            //admin can switch to other excel
            console.log("enable excel sw");
            $("#target").data("kendoDropDownList").enable(true);
            $("#target").closest(".k-widget").show();
            $("#target_txt").text('');
            var dropdownlist = $("#target").data("kendoDropDownList");
            dropdownlist.bind("change", onTargetChange);
        }
    } else if (status=='queued') {
        //$('#quote-rate').prop('readonly', false);
        $("#quote-rate").data("kendoNumericTextBox").readonly(false);
        //$('#complete-rate').hide();
        $("#complete-rate").data("kendoNumericTextBox").wrapper.hide();
        //$('#target').attr('disabled', false);
        $("#target").data("kendoDropDownList").enable(true);
        //$('#ap_button').show();
        $('#ap_button').attr('disabled', true);
        $('#ex_button').hide();//
        $('#de_button').show();
        $('#rp_button').show();
        //$('.actions').hide();
        $('#grid').data("kendoGrid").hideColumn("action");
    } else if (status=='declined') {
        //$('#quote-rate').prop('readonly', true);
        $("#quote-rate").data("kendoNumericTextBox").wrapper.hide();
        //$('#complete-rate').prop('readonly', true);
        $("#complete-rate").data("kendoNumericTextBox").wrapper.hide();
        //$('#target').attr('disabled', true);
        $("#target").data("kendoDropDownList").enable(false);
        $("#target").closest(".k-widget").hide();
        $("#target_txt").text(target_name);

        $('#ap_button').hide();
        $('#ex_button').hide();//attr('disabled', false);
        $('#de_button').hide();
        $('#rp_button').hide();
        //$('.actions').hide();
        $('#grid').data("kendoGrid").hideColumn("action");
    } else if (status=='completed') {
        //$('#quote-rate').prop('readonly', true);
        //$('#complete-rate').prop('readonly', true);
        $("#quote-rate").data("kendoNumericTextBox").value(quote_rate);
        $("#quote-rate").data("kendoNumericTextBox").readonly(true);
        //$('#complete-rate').prop('readonly', false);
        $("#complete-rate").data("kendoNumericTextBox").value(complete_rate);
        $("#complete-rate").data("kendoNumericTextBox").readonly(true);
        //$('#target').attr('disabled', true);

        $("#target").data("kendoDropDownList").enable(false);
        $("#target").closest(".k-widget").hide();
        $("#target_txt").text(target_name);

        $('#ap_button').hide();
        $('#ex_button').hide();//attr('disabled', false);
        $('#de_button').hide();
        $('#rp_button').show();
        $('#grid').data("kendoGrid").hideColumn("action");
    } else {  // no status
        //$('#quote-rate').prop('readonly', true);
        //$('#complete-rate').prop('readonly', true);
        //$('#target').attr('disabled', true);
        $("#quote-rate").data("kendoNumericTextBox").wrapper.hide();
        $("#complete-rate").data("kendoNumericTextBox").wrapper.hide();
        $("#target").data("kendoDropDownList").enable(false);
        $('#ap_button').hide();
        $('#ex_button').hide();
        $('#de_button').hide();
        $('#rp_button').hide();
        //$('.actions').hide();
        $('#grid').data("kendoGrid").hideColumn("action");
    }

    $('#ap_button').click(function(event) {
        var target = $("#target").val();
        console.log("target:"+target);
        console.log("q_rate:"+$("#quote-rate").val());

        if (target=='') {
            alert("Please select a Channel.");
        } else {
            if (confirm("Are you sure you want to Approve this batch?"))
                updateStatus("processing");
        }
    });

    $('#de_button').click(function(event) {
        if (confirm("Are you sure you want to Decline this batch?"))
            updateStatus("declined");
    });
    $('#cp_button').click(function(event) {
        if (confirm("Are you sure you want to Complete this batch?"))
            updateStatus("completed");
    });

    $('#ex_button').click(function(event) {
        var id= batch_id;    //"<?=$remittanceBatch[0]['batch_id']?>";
        var url= $("#excel_url").val();
        var target = $("#target").val();
        url = url + '?batch_id='+id+'&target='+target;
        window.open(url);

        return false;
        //$(location).attr('href', url);
    });
    $('#rp_button').click(function(event) {
        var id= batch_id;   //"<?=$remittanceBatch[0]['batch_id']?>";
        var url= $("#report_url").val();
        url = url + '?batch_id='+id +'&status='+ status;
        window.open(url);
        return false;
    });

    $('.act-ok').click(function(event) {
        console.log('ok clicked');
        //console.log($(this).attr('data-id'));
        event.preventDefault();
        //updateStatus("declined");
        updateLogStatus($(this).attr('data-bid'),$(this).attr('data-id'),'ok');
    });
    $('.act-fail').click(function(event) {
        event.preventDefault();
        console.log($(this).attr('data-id'));
        updateLogStatus($(this).attr('data-bid'),$(this).attr('data-id'),'fail');
    });

     $("#cdialog").kendoDialog({
         width: 320,
         height: 260,
         title: "WeCollect",
         visible: false,
         actions: [{
             text: "OK",
             action: function(e){
                 // e.sender is a reference to the dialog widget object
                 // OK action was clicked
                 // Returning false will prevent the closing of the dialog
                 return true;
             },
             primary: true
         }],
     }).data("kendoDialog");

    /*
    console.log(target_data.value());
    console.log(target_data.text());
     */
    /*
    var dataItem = targetDs.data(); //.get(target_data.value());
    console.log(dataItem);
    */

    /*
        var dialog = $("#cdialog").data("kendoDialog");
        dialog.content("Please select another channel, ");
        dialog.open();
    */
});
//end ready

/*
 $("#ex_button").kendoButton({
 //enable: false
 });
 */
/*
 $('a.act-ok').click(function(event) {
 console.log('ok clicked');
 console.log($(this).attr('data-id'));
 //event.preventDefault();
 });

 $('.act-all-ok').click(function(event) {
 var bid = $("#batch_id").val();
 event.preventDefault();
 console.log('bid:'+bid);
 //updateLogStatus($(this).attr('data-bid'),'all','ok');
 });
 */
function updateStatus(status) {
    var id= batch_id; //"<?=$remittanceBatch[0]['batch_id']?>";
    var url= $("#update_url").val();
    var target = $("#target").val();
    var q_rate = $("#quote-rate").val();
    var c_rate = $("#complete-rate").val();

    console.log("id:"+id+" s:"+status+" url:"+url);
    var dialog = $("#cdialog").data("kendoDialog");

    $.post(url,'batch_id='+id+'&status='+status+'&target='+target+'&q_rate='+q_rate+'&c_rate='+c_rate, function(data) {
        console.log("return:"+data.status);
        if (data.status==0) {
            location.reload();
        } else if (data.status==-2 && data.processor !=null) {
            dialog.content("Please select another channel, <br/>"+data.processor+" cannot process remittance for "+data.bank);
            dialog.open();
        } else if (data.msg !=null) {
            dialog.content(data.msg);
            dialog.open();
        }
    }, 'json');

    console.log('end post');
    //location.reload();
}

function updateLogStatus(bid,id,status,val) {
    var url= $("#updatelog_url").val();
    if (bid=='')
        bid = batch_id; //$("td#batch_id").text();
    //default value
    val = (typeof val !== 'undefined') ?  val : null;
    console.log("id:"+id+" s:"+status+",bid:"+bid+" url:"+url+" v:"+val);
    //return true;

    if (confirm("Are you sure you want to set "+status+" ?")) {
        $.post(url, 'batch_id=' + bid + '&id=' + id + '&status=' + status + '&value=' + val, function (data) {
            console.log("return:" + data.status);
            if (data.status == 0) {
                //show updated total
                //if (status=='fail')
                location.reload();
                //$('#grid').data('kendoGrid').dataSource.read();
                //$('#GridName').data('kendoGrid').refresh();
                return true;
            }
        }, 'json');

        console.log('end post');
    }
    return false;
}

function showApiLog(bid,id) {
    var url= $("#apilog_url").val();
    var dialog = $("#cdialog").data("kendoDialog");

    if (bid=='')
        bid = batch_id; //$("td#batch_id").text();
    console.log("id:"+id+", bid:"+bid);

    $.post(url,'batch_id='+bid+'&id='+id, function(data) {
        console.log("return:"+data.status);
        if (data.status==0) {
            //show updated total
            //if (status=='fail')
            //location.reload();
            //$('#grid').data('kendoGrid').dataSource.read();
            //return true;
            dialog.content("Count: "+data.total+"<br/>Message: "+data.msg);
            dialog.open();
        }
    }, 'json');

    //console.log('end post');
    return false;
}

function onTBChange() {
    //console.log("Change :: " + this.value());
    var target = $("#target").data("kendoDropDownList").value();  //$("#target").val();
    var q_rate = $("#quote-rate").data("kendoNumericTextBox").value();  //.val();
    var c_rate = $("#complete-rate").data("kendoNumericTextBox").value(); //$("#complete-rate").val();
    //Approve

    //if (target!='' && q_rate>0) {}
    $('#ap_button').attr('disabled', (target=='' || q_rate==0));
    //Complete
    $('#cp_button').attr('disabled', (!readyToComplete()));
}
function readyToComplete() {
    if ($("#complete-rate").data("kendoNumericTextBox").value()>0 && ($('#log_ready').val()>0 || $('#log_ready').val()=='true') ) {
        console.log("complete ok");
        return true;
    }
    console.log("complete ng");
    return false;
}
function onTargetChange(e) {
    var val = this.value();
    console.log("onTargetChange:"+val);

    var url= $("#update_url").val();
    var bid = batch_id; //$("td#batch_id").text();
    console.log("bid:"+bid+" url:"+url+" v:"+val);
    //return true;

    if (confirm("Are you sure you want to change the Channel?")) {
        $.post(url, 'batch_id=' + bid + '&set_target=1&target=' + val, function (data) {
            console.log("return:" + data.status);
            if (data.status == 0) {
                //show updated total
                //if (status=='fail')
                location.reload();
                return true;
            }
        }, 'json');
    } else {
        location.reload();
    }

    //console.log('end post');
    return false;
}