<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>jQuery UI Fullscreen</title>
    
    <!-- jQuery und jQuery UI von Google gehostet -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
    
    <script src="dbdesigner.js"></script>
    
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        #fullscreen-div {
            width: 100vw;
            height: 100vh;
            background-color: lightgray;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-family: Arial, sans-serif;
            position: relative;
        }
    </style>
</head>
<body>
    <div id="fullscreen-div"></div>
    
    <script>

var exportedData = {
    data: [
        {
            id: 123,
            name: "Contact",
            fields: [{ name: "id", type: "INTEGER" }, { name: "name", type: "TEXT" }],
            primaryKeys: ["id"],
            position: { x: 100, y: 200 }
        },
        {
            id: 124,
            name: "Address",
            fields: [{ name: "id", type: "INTEGER" }, { name: "contactId", type: "INTEGER" }],
            primaryKeys: ["id"],
            position: { x: 300, y: 200 }
        }
    ],
    foreignKeys: [
        { from: { tableId: 123, fieldName: "id" }, to: { tableId: 124, fieldName: "contactId" } }
    ]
};



        $(function() {

            // create DBDesigner
            var dbd = $('#fullscreen-div').dbdesigner({

                // use change event
                "onchange": function(e) {
                    console.log(e);
                }

            });

            // empty DBDesigner
            dbd.clear();

            // initialise DBDesigner
            dbd.initializeFromData(exportedData);

            // get data from DBDesigner
            var data = dbd.getData();
            console.log(dbd.getData());

        });
    </script>
</body>
</html>

