<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>STEMMechanics</title>
	<style>
	#app-loader-container{position:fixed;display:flex;top:0;justify-content:center;align-items:center;left:0;height:100vh;width:100vw;z-index:10000;backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);background-color:rgba(255,255,255,.5);opacity:1;transition:opacity .1s ease-in}#app-loader{display:inline-block;position:relative;width:80px;height:80px}#app-loader div{position:absolute;top:33px;width:13px;height:13px;border-radius:50%;background:#000;animation-timing-function:cubic-bezier(0,1,1,0);box-shadow:0 0 1px #000}#app-loader div:first-child{left:8px;animation:.6s infinite app-loading-icon1}#app-loader div:nth-child(2){left:8px;animation:.6s infinite app-loading-icon2}#app-loader div:nth-child(3){left:32px;animation:.6s infinite app-loading-icon2}#app-loader div:nth-child(4){left:56px;animation:.6s infinite app-loading-icon3}@keyframes app-loading-icon1{0%{transform:scale(0)}100%{transform:scale(1)}}@keyframes app-loading-icon3{0%{transform:scale(1)}100%{transform:scale(0)}}@keyframes app-loading-icon2{0%{transform:translate(0,0)}100%{transform:translate(24px,0)}}
	</style>
</head>
<body>
	<div id="app-loader-container"><div id="app-loader"><div></div><div></div><div></div><div></div></div></div>
	<div id="app"></div>
	@vite('resources/js/main.js')
	<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
	<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
	<script type="text/javascript">
	window.addEventListener("load",function(){let e=document.getElementById("app-loader-container");e.style.opacity=0,window.setTimeout(()=>{e.parentNode.removeChild(e)},125)});
	</script>
</body>
</html>
