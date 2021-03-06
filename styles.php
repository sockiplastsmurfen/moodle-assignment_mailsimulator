<?php
header("Content-type: text/css");
?>

.mailboxwrapper table, tbody, tfoot, thead, tr, th, td {
	margin: 0;
	padding: 0;
	#border: 0;
	border-collapse: collapse;
	border-spacing: 0px 0px;
	font-size: 100%;
	font: inherit;
	vertical-align: top;
}

.mailboxwrapper img {
	border: 0;
}

.mailtoptitle{
        margin-top: 3px;
        font-size: 90%;
	text-align: center;
}

.mailmidletable {
	height: 200px;
	border: 1px;
	border-style: solid;
	border-color: #999999;
	border-width: 1px;
        background-color: #ffffff;
}

.mailboxheader {
        font-size: 90%;
        padding: 4px;
        font-weight: bold;
        color: #666666;
}

.mailboxes {        
	width: 100px;
	background-image: url(images/mailboxes-bg.png);
}

.mailbox {
        font-size: 80%;
        height 24px;      
}

.mailbox img {
        vertical-align: text-bottom;
}

.mailboxselect {
        font-size: 80%;
        height 24px;
        background-image: url(images/mailbox-selected.png);
}

.mailboxselect img {
        vertical-align: text-bottom;
}

.mailheaders {
	width: 300px;
	border-left-style: solid;
	border-left-color: #999999;
	border-left-width: 1px;
	border-right-style: solid;
	border-right-color: #999999;
	border-right-width: 1px;
}

.mailmessage {
    padding: 5px;
    background: #ffffff;

}

mailheadertd {
	margin: 10px;
	padding: 10px;
}

.statusimg {
        width: 16px;
        margin: 0;
	padding: 0;
}

.mailheadertable {	
	border-bottom-style: solid;
	border-bottom-color: #999999;
	border-bottom-width: 1px;
        cursor:pointer;
        font-size: 10px;
}

.mailheadertableselected {  	
	border-bottom-style: solid;
	border-bottom-color: #999999;
	border-bottom-width: 1px;
        color: #ffffff;
        background-image: url(images/header-selected-bg.png);
}

.mailheadertable td {
    font-size: 90%;
}

.mailheadertableselected td{
    font-size: 90%;
}

.headerdate {
	text-align: right;
        color: #3366cc;
}

.headerdateselected {
	text-align: right;
        color: #ffffff;
}

.allmailheader {
        background:lightgray;
        width: 100%;
        border-top: 1px;
        border-top-style: solid;
        font-size: 90%;
}

.allmailheader td {
        padding-left: 5px;
        padding-right: 10px;
        padding-top: 2px;
}


/* ----- Background images ----- */

.shadow-bottom-bg {
	background-repeat: repeat-x;
	background-image: url(images/shadow-bg-bottom.png);
}
.shadow-left-bg {
	background-repeat: repeat-y;
	background-image: url(images/shadow-bg-left.png);
}
.shadow-right-bg {
	background-repeat: repeat-y;
	background-image: url(images/shadow-bg-right.png);
}
.window-top-bg {
	background-repeat: repeat-x;
	background-image: url(images/window-top-bg.png);
}


div.scroll {
        height: 100%;
        overflow: auto;
}