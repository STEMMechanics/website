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
		.sm-html .ProseMirror {
			outline: none;
    	}

		.sm-html hr {
			border-top: 1px solid #aaa;
			margin: 1.5rem 0;
		}

		.sm-html pre {
			padding: 0 1rem;
			line-height: 1rem;
		}

		.sm-html blockquote {
			border-left: 4px solid #ddd;
			margin-left: 1rem;
			padding-left: 1rem;
		}

		.sm-html p.info {
			display: flex;
			border: 1px solid rgba(14,165,233,1);
			background-color: rgba(14,165,233,0.25);
			border-radius: 0.5rem;
			padding: 0.5rem 1rem 0.5rem 0.75rem;
			margin: 0.5rem;
			font-size: 80%;
		}

		.sm-html p.info::before {
			content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' %3E%3Cpath d='M11,9H13V7H11M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M11,17H13V11H11V17Z' fill='currentColor' /%3E%3C/svg%3E");
			display: inline-block;
			color: rgba(14,165,233,1);
			width: 1.5rem;
			height: 1.5rem;
			margin-right: 0.5rem;
			margin-top: 0.1rem;
		}

		.sm-editor::-webkit-scrollbar {
			background-color: transparent;
			width: 16px;
		}

		.sm-editor::-webkit-scrollbar-thumb {
			background-color: #aaa;
			border: 4px solid transparent;
			border-radius: 8px;
			background-clip: padding-box;  
		}

		@keyframes rotate{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
</head>
<body>
	<div id="app"></div>
	@vite('resources/js/main.js')
</body>
</html>
