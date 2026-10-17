<section class="auth-wrap container auth-short"><div class="auth-info"><div class="eyebrow">WELCOME BACK</div><h1>Pick up <em>where you left off!</em></h1><p>The adventure is waiting. Log in with your username and the secret return code you were given.</p><div class="auth-illustration">🛡️ <span>⚡</span> 🎮</div></div>
<div class="auth-panel"><div class="panel-head"><div class="eyebrow">PLAYER LOGIN</div><h2>Hello Again!</h2></div>
<form method="post" action="/login" class="auth-form"><input type="hidden" name="_csrf" value="<?=e(csrf())?>">
<label class="field"><span>Username</span><input name="username" required autocomplete="username" placeholder="Your username"></label>
<label class="field"><span>Your 4-Digit Login Code</span><input name="recovery_code" required minlength="4" maxlength="4" pattern="[0-9]{4}" inputmode="numeric" autocomplete="one-time-code" placeholder="••••" aria-describedby="code-purpose"></label>
<p class="small-tip" id="code-purpose"><b>What is this code for?</b><br>The personal code you got when you registered lets you get back to the same player account and your points without creating a password. Never share your code with anyone.</p>
<button class="btn btn-primary btn-block btn-xl">Go to Player Dashboard <span>➜</span></button><p class="helper-text">Don't have an account yet? <a href="/register">Sign Up Now</a></p>
</form></div></section>
