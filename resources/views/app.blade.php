<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>STEMMechanics</title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link rel="preload" as="image" href="https://www.stemmechanics.com.au/assets/home-hero.webp">
	<link
		rel="preload"
		href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,700;1,400;1,700&display=swap"
		as="style"
		onload="this.onload=null;this.rel='stylesheet'"
	/>
	<noscript>
		<link
			href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,700;1,400;1,700&display=swap"
			rel="stylesheet"
			type="text/css"
		/>
	</noscript>
	<style>
		*, :after, :before { box-sizing: border-box; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; border: 0 solid #e5e7eb; }
        html { overflow: -moz-scrollbars-vertical; overflow-y: auto; background-color: #f3f4f6; }
        html, body { font-family: Poppins, Roboto, "Open Sans", ui-sans-serif, system-ui, sans-serif; font-size: 1rem; color: #000; width: 100%; min-height: 100vh; min-width: 100%; overflow-x: hidden; line-height: 1.5; margin: 0; }
		h1, h2, h3, p { margin: 0; }
		p + p, p + ul, ul + p { margin-top: 1rem; }
        ol, ul { list-style: none; margin: 0; padding: 0; }
		.list-decimal, .list-disc, .list-circle { margin-left: 2.5rem; }
		.list-decimal li, .list-disc li, .list-circle li { margin-top: 0.5rem; }
		a:not([role="button"]) { color: #0284c7; }
		a:not([role="button"]):hover { color: #0ea5e9; }
		a[role="button"] { text-decoration: inherit; }
		input:disabled { background-color: rgba(243, 244, 246); }
		input[type="submit"]:disabled { background-color: rgba(209, 213, 219); }
		input { font-family: Poppins, Roboto, "Open Sans", ui-sans-serif, system-ui, sans-serif; }
		.scrollbar-width-none { scrollbar-width: none; }
		.scrollbar-width-none::-webkit-scrollbar { display: none; }
		.spin{animation:rotate 1s infinite linear}
		@keyframes rotate{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
</head>
<body>
	<div id="app"></div>
	@vite('resources/js/main.js')
</body>
</html>
