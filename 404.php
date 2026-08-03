<?php 
// --- 1. SEND 404 HEADER ---
// This ensures search engines and browsers treat this as a true error page.
http_response_code(404);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LingoFlow v2 - System Error 404</title>
    <style>
      /* --- 1. CSS VARIABLES & RESET --- */
      :root {
        --bg-deep: #020617;
        --text-main: #e2e8f0;
        --text-muted: #94a3b8;
        --violet: #8b5cf6;
        --violet-glow: rgba(139, 92, 246, 0.5);
        --cyan: #06b6d4;
        --green: #22c55e;
        --red: #ef4444;
        --glass: rgba(255, 255, 255, 0.03);
        --border: rgba(255, 255, 255, 0.1);
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: "Segoe UI", sans-serif;
        background-color: var(--bg-deep);
        color: var(--text-main);
        /* Background Effect */
        background-image: radial-gradient(
          circle at 50% 0%,
          rgba(239, 68, 68, 0.1) 0%,
          transparent 50%
        );
        height: 100vh;
        overflow: hidden;
        /* Center Content */
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
      }

      /* Scanline Effect */
      body::after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(
          to bottom,
          rgba(255, 255, 255, 0) 50%,
          rgba(0, 0, 0, 0.2) 50%
        );
        background-size: 100% 4px;
        z-index: 1;
        pointer-events: none;
      }

      /* --- 2. ERROR CARD DESIGN --- */
      .error-container {
        text-align: center;
        z-index: 2;
        padding: 40px;
        background: var(--glass);
        backdrop-filter: blur(10px);
        border: 1px solid var(--border);
        border-radius: 24px;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        max-width: 500px;
        width: 90%;
        position: relative;
        overflow: hidden;
      }

      /* Glitchy Border Top */
      .error-container::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--red), var(--violet));
        box-shadow: 0 0 20px var(--red);
      }

      .glitch-text {
        font-size: 80px;
        font-weight: 900;
        color: var(--red);
        margin: 0;
        position: relative;
        display: inline-block;
        text-shadow: 2px 2px var(--cyan);
        animation: glitch-skew 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) infinite
          alternate-reverse;
      }

      .glitch-text::before,
      .glitch-text::after {
        content: attr(data-text);
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--bg-deep);
      }

      .glitch-text::before {
        left: 2px;
        text-shadow: -2px 0 var(--violet);
        clip: rect(44px, 450px, 56px, 0);
        animation: glitch-anim 5s infinite linear alternate-reverse;
      }

      .glitch-text::after {
        left: -2px;
        text-shadow: -2px 0 var(--cyan);
        clip: rect(44px, 450px, 56px, 0);
        animation: glitch-anim2 1s infinite linear alternate-reverse;
      }

      h2 {
        font-size: 24px;
        margin: 10px 0 20px 0;
        text-transform: uppercase;
        letter-spacing: 2px;
      }

      p {
        color: var(--text-muted);
        margin-bottom: 30px;
        line-height: 1.6;
      }

      /* Button */
      .button-home {
        padding: 14px 30px;
        background: transparent;
        color: var(--violet);
        border: 2px solid var(--violet);
        border-radius: 12px;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        display: inline-block;
        text-transform: uppercase;
        letter-spacing: 1px;
      }

      /* --- 3. ANIMATIONS --- */
      @keyframes glitch-anim {
        0% {
          clip: rect(31px, 9999px, 94px, 0);
        }
        20% {
          clip: rect(6px, 9999px, 8px, 0);
        }
        40% {
          clip: rect(66px, 9999px, 14px, 0);
        }
        60% {
          clip: rect(98px, 9999px, 88px, 0);
        }
        80% {
          clip: rect(2px, 9999px, 30px, 0);
        }
        100% {
          clip: rect(53px, 9999px, 5px, 0);
        }
      }

      @keyframes glitch-anim2 {
        0% {
          clip: rect(65px, 9999px, 92px, 0);
        }
        20% {
          clip: rect(10px, 9999px, 2px, 0);
        }
        40% {
          clip: rect(22px, 9999px, 56px, 0);
        }
        60% {
          clip: rect(89px, 9999px, 11px, 0);
        }
        80% {
          clip: rect(3px, 9999px, 44px, 0);
        }
        100% {
          clip: rect(44px, 9999px, 2px, 0);
        }
      }

      @keyframes glitch-skew {
        0% {
          transform: skew(0deg);
        }
        20% {
          transform: skew(-2deg);
        }
        40% {
          transform: skew(2deg);
        }
        60% {
          transform: skew(-1deg);
        }
        80% {
          transform: skew(1deg);
        }
        100% {
          transform: skew(0deg);
        }
      }
    </style>
  </head>
  <body>
    <div class="error-container">
      <div class="glitch-text" data-text="404">404</div>
      <h2>System Malfunction</h2>
      <p>
        The requested data packet could not be retrieved from the mainframe. The
        page you are looking for has been corrupted or moved to a secure sector.
      </p>
      <!-- Updated Link to PHP Dashboard -->
      <a href="index.php" class="button-home">Return to Base</a>
    </div>
  </body>
</html>
