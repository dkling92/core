function getVirtualDirectory() {
  var vDir = document.location.pathname.split('/');
  return '/' + vDir[1] + '/';
}

function include_jQueryFilesToPage() {
  var siteAddress = location.protocol + '//' + document.location.hostname + getVirtualDirectory(); 
  var jqCSSFilePath =  siteAddress + 'includes/jQueryCSS/ehrgreen-theme/jquery-ui-1.8.2.custom.css';
  var jqCoreFilePath = siteAddress + 'includes/jquery-1.4.1.min.js';
  var jqUIFilePath =   siteAddress + 'includes/jquery-ui-1.8.2.custom.min.js';
  var head  = document.getElementsByTagName('head')[0]; 

  // jQuery CSS jnclude
  var jqCSS = 'cssIDJQ';  // you could encode the css path itself to generate id.
  if (!document.getElementById(jqCSS)) { 
    var link  = document.createElement('link'); 
    link.id  = jqCSS; 
    link.rel  = 'stylesheet'; 
    link.type = 'text/css'; 
    link.href = jqCSSFilePath; 
    link.media = 'all'; 
    head.appendChild(link); 
  } 

  // Core jQuery include
  var jqc = "coreFileRefIDJQ";  
  if (!document.getElementById(jqc)) 
    document.write('<scr' + 'ipt type="text/javascript" id="' + jqc + '" src="' + jqCoreFilePath + '"></scr' + 'ipt>');

  // jQueryUI include
  var jqUI = "uiFileRefIDJQ";  
  if (!document.getElementById(jqUI)) 
    document.write('<scr' + 'ipt type="text/javascript" id="' + jqUI + '" src="' + jqUIFilePath + '"></scr' + 'ipt>');
}

include_jQueryFilesToPage();