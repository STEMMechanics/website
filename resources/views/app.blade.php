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
		small { display: inline-block; }
		.small { font-size: smaller; }
		.list-decimal, .list-disc, .list-circle { margin-left: 2.5rem; }
		.list-decimal li, .list-disc li, .list-circle li { margin-top: 0.5rem; }
		a:not([role="button"]) { color: #0284c7; }
		a:not([role="button"]):hover { color: #0ea5e9; }
		a[role="button"] { text-decoration: inherit; }
		a{text-decoration: none;}
		input:disabled { background-color: rgba(243, 244, 246); }
		input[type="submit"]:disabled { background-color: rgba(209, 213, 219); }
		input { font-family: Poppins, Roboto, "Open Sans", ui-sans-serif, system-ui, sans-serif; }
		.scrollbar-width-none { scrollbar-width: none; }
		.scrollbar-width-none::-webkit-scrollbar { display: none; }
		.bg-center { background-position: center; }
		.whitespace-nowrap {white-space: nowrap; }
		.spin{animation:rotate 1s infinite linear}
		.text-xxs { font-size: 0.6rem; line-height: 0.75rem; }
		.text-bold { font-weight: bold; }
		.sm-html .ProseMirror { outline: none; }
		.sm-html hr { border-top: 1px solid #aaa; margin: 1.5rem 0; }
		.sm-html pre { padding: 0 1rem; line-height: 1rem; }
		.sm-html blockquote { border-left: 4px solid #ddd; margin-left: 1rem; padding-left: 1rem; }
		.sm-html p.info, .sm-html p.success, .sm-html p.warning, .sm-html p.danger { display: flex; border-radius: 0.5rem; padding: 0.5rem 1rem 0.5rem 0.75rem; margin: 0.5rem; font-size: 80%; }
		.sm-html p.info::before, .sm-html p.success::before, .sm-html p.warning::before, .sm-html p.danger::before { display: inline-block; width: 1.5rem; height: 1.5rem; margin-right: 0.5rem; margin-top: 0.1rem; }
		.sm-html p.info { border: 1px solid rgba(14,165,233,1); background-color: rgba(14,165,233,0.25); }
		.sm-html p.info::before { color: rgba(14,165,233,1); content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' %3E%3Cpath d='M11,9H13V7H11M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M11,17H13V11H11V17Z' fill='rgba(14,165,233,1)' /%3E%3C/svg%3E"); }
		.sm-html p.success { border: 1px solid rgba(22,163,74,1); background-color: rgba(22,163,74,0.25); }
		.sm-html p.success::before { color: rgba(22,163,74,1); content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' %3E%3Cpath d='M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20M16.59 7.58L10 14.17L7.41 11.59L6 13L10 17L18 9L16.59 7.58Z' fill='rgba(22,163,74,1)' /%3E%3C/svg%3E"); }
		.sm-html p.warning { border: 1px solid rgba(202,138,4,1); background-color: rgba(250,204,21,0.25); }
		.sm-html p.warning::before { color: rgba(202,138,4,1); content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' %3E%3Cpath d='M12,2L1,21H23M12,6L19.53,19H4.47M11,10V14H13V10M11,16V18H13V16' fill='rgba(202,138,4,1)' /%3E%3C/svg%3E"); }
		.sm-html p.danger { border: 1px solid rgba(220,38,38,1); background-color: rgba(220,38,38,0.25); }
		.sm-html p.danger::before { color: rgba(220,38,38,1); content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' %3E%3Cpath d='M8.27,3L3,8.27V15.73L8.27,21H15.73L21,15.73V8.27L15.73,3M8.41,7L12,10.59L15.59,7L17,8.41L13.41,12L17,15.59L15.59,17L12,13.41L8.41,17L7,15.59L10.59,12L7,8.41' fill='rgba(220,38,38,1)' /%3E%3C/svg%3E"); }
		.sm-html img { display: block; margin: 1rem auto; max-height: 100%; max-width: 100%; }
		.sm-html ul { list-style: disc; margin: 1rem 2rem; }
		.sm-html ol { list-style: decimal; margin: 1rem 2rem; }
		.sm-html ul li, .sm-html ol li { margin-bottom: 0.25rem; }
		.sm-editor::-webkit-scrollbar { background-color: transparent; width: 16px; }
		.sm-editor::-webkit-scrollbar-thumb { background-color: #aaa; border: 4px solid transparent; border-radius: 8px; background-clip: padding-box; }
		.selected-checked { border: 3px solid rgba(2,132,199,1); position: relative; }
		.selected-checked::after { display: block; position: absolute; border:1px solid white; height: 1.5rem; width: 1.5rem; background-color: rgba(2,132,199,1); top: -0.4rem; right: -0.4rem; content: ""; background-position: center; background-repeat: no-repeat; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M21,7L9,19L2.712,12.712L5.556,9.892L9.029,13.358L18.186,4.189L21,7Z' fill='rgba(255,255,255,1)' /%3E%3C/svg%3E")}
		@keyframes rotate{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
</head>
<body>
	<div id="app"></div>
	@vite('resources/js/main.js')
</body>
</html>
