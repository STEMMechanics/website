<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Site Maintenance</title>
  <style>
    :root {
      color-scheme: light dark;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: clamp(1rem, 5vw, 3rem);
      font: clamp(1rem, 1.7vw, 1.125rem) Helvetica, Arial, sans-serif;
      color: #333;
      text-align: center;
      box-sizing: border-box;
    }

    article {
      width: min(40rem, 100%);
      text-align: left;
    }

    img {
      display: block;
      width: min(100%, 19.5rem);
      height: auto;
      margin: 0 auto 1rem;
    }

    h1 {
      margin: 0 0 1rem;
      text-align: center;
      line-height: 1.2;
      font-size: clamp(1.5rem, 5.8vw, 2.25rem);
    }

    p {
      margin: 0 0 1rem;
      line-height: 1.5;
    }

    a, a:visited {
      color: #35a5f1;
      text-decoration-thickness: 0.1em;
      text-decoration-color: #67bbf4;
    }

    a:hover {
      filter: brightness(115%);
    }

    .d-light { display: block; }
    .d-dark { display: none; }

    @media (prefers-color-scheme: dark) {
      body { background-color: #333; color: #ccc; }
      .d-light { display: none; }
      .d-dark { display: block; }
    }
  </style>
</head>
<body>

<article>
    <img class="d-light" src="/logo.svg" width="312" height="48" alt="STEMMechanics" />
    <h1>We&rsquo;ll be back soon!</h1>
    <div>
        <p>Sorry for the inconvenience but we&rsquo;re performing some maintenance at the moment. If you need to you can always contact us by <a href="mailto:hello@stemmechanics.com.au">email</a>, otherwise we&rsquo;ll be back online shortly!</p>
        <p>&mdash; The STEMMechanics Team</p>
    </div>
</article>
</body>
</html>
