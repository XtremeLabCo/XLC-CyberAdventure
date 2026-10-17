'use strict';
// Educational password strength estimate that runs entirely in the browser. The password is never sent anywhere.
// Attackers try patterns, not random characters: common passwords, dictionary words,
// capitalised word + year + exclamation mark, digits for letters (P@ssw0rd). So each part is scored separately.
(() => {
  const COMMON = new Set(['123456','1234567','12345678','123456789','1234567890','12345','1234','111111','000000','123123','654321','121212','qwerty','qwerty123','qwertyuiop','asdfgh','asdfghjkl','zxcvbnm','1q2w3e','1q2w3e4r','1qaz2wsx','abc123','password','password1','password123','passw0rd','iloveyou','letmein','welcome','welcome1','admin','admin123','monkey','dragon','football','baseball','sunshine','princess','master','shadow','trustno1','superman','batman','michael','jennifer','starwars','whatever','freedom','hello','charlie','donald','login','qazwsx','mustang','access','flower','hottie','loveme','666666','7777777','888888','696969','ashley','bailey','passpass']);
  const WORDS = new Set(['london','paris','newyork','chicago','boston','sydney','toronto','dublin','berlin','madrid','rome','tokyo','texas','california','florida','liverpool','chelsea','arsenal','barcelona','juventus','manchester','united','tottenham','everton','celtic','yankees','lakers','cowboys','patriots','steelers','eagles','giants','dodgers','bulls','celtics','password','admin','welcome','login','user','guest','school','class','game','games','play','player','summer','winter','spring','autumn','january','february','march','april','may','june','july','august','september','october','november','december','monday','tuesday','wednesday','thursday','friday','saturday','sunday','red','blue','green','yellow','black','white','orange','purple','pink','silver','gold','brown','cat','dog','lion','tiger','bear','wolf','eagle','horse','rabbit','monkey','dragon','fish','bird','cloud','river','apple','tree','house','car','star','moon','sun','sky','sea','ocean','rain','snow','fire','water','earth','stone','rock','book','pen','cake','coffee','tea','lemon','flower','garden','beach','castle','mountain','forest','leaf','family','baby','honey','angel','love','lovely','heart','happy','music','money','secret','magic','john','michael','david','james','robert','william','thomas','daniel','matthew','chris','emma','olivia','ethan','sophia','liam','noah','ava','mia','emily','jessica','sarah','anna','lucy','jack','harry','oliver','charlie','george','grace','mom','dad','mum','mother','father','sister','brother','friend','abc','qwerty','asdf','hello']);
  const LEET = {'@':'a','4':'a','0':'o','3':'e','1':'i','$':'s','5':'s','7':'t'};
  const GUESSES_PER_SECOND = 1e6; // assumes a fast computer attacking a leaked password database

  function isSequence(digits) {
    if (/^(\d)\1+$/.test(digits)) return true;
    let up = true, down = true;
    for (let i = 1; i < digits.length; i++) { const d = digits.charCodeAt(i) - digits.charCodeAt(i - 1); up = up && d === 1; down = down && d === -1; }
    return digits.length >= 3 && (up || down);
  }
  function letterBits(chunk) {
    const lower = chunk.toLocaleLowerCase('en-US');
    const caps = chunk === lower ? 0 : (chunk[0] !== lower[0] && chunk.slice(1) === lower.slice(1) ? 1 : 3);
    if (WORDS.has(lower) || COMMON.has(lower)) return 10 + caps;
    if (chunk.length <= 3) return chunk.length * 4.7 + caps;
    if (/[aeiou]/.test(lower) && chunk.length <= 12) return 15 + caps; // like a word from a large dictionary
    return chunk.length * 4.7 + caps;
  }
  function estimate(value) {
    const raw = String(value || '');
    if (!raw) return { bits: 0, seconds: 0, common: false };
    const lower = raw.toLocaleLowerCase('en-US');
    if (COMMON.has(lower) || COMMON.has(lower.replace(/[^a-z0-9]/g, ''))) return { bits: 4, seconds: 0, common: true };
    let leet = 0;
    const plain = raw.replace(/(?<=\p{L})[@403$157](?=\p{L}|$)|(?<=^|\p{L})[@403$157](?=\p{L})/gu, c => { leet++; return LEET[c]; });
    const parts = plain.match(/\p{L}+|\p{N}+|[^\p{L}\p{N}]+/gu) || [];
    let bits = Math.min(leet, 3) * 1 + Math.log2(Math.max(1, parts.length));
    let common = COMMON.has(plain.toLocaleLowerCase('en-US'));
    for (const part of parts) {
      if (/^\p{L}+$/u.test(part)) {
        for (const chunk of part.split(/(?<=\p{Ll})(?=\p{Lu})/u)) { bits += letterBits(chunk); common = common || COMMON.has(chunk.toLocaleLowerCase('en-US')); }
      } else if (/^\p{N}+$/u.test(part)) {
        bits += /^(19|20)\d\d$/.test(part) ? 7 : /^(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(19|20)?\d\d$/.test(part) ? 15 : isSequence(part) ? 3 : part.length * 3.32; // year, birth date, 1234
      } else {
        bits += /^\s+$/.test(part) || /^[-_.]$/.test(part) ? 1 : part.length === 1 ? 3 : part.length * 4;
      }
    }
    if (/(.)\1{2,}/u.test(raw)) bits *= 0.75;
    const half = raw.length / 2;
    if (raw.length % 2 === 0 && raw.slice(0, half) === raw.slice(half)) bits = bits / 2 + 1; // same chunk repeated
    return { bits, seconds: 2 ** bits / GUESSES_PER_SECOND, common };
  }
  function timeText(seconds) {
    if (seconds < 1) return 'Instantly';
    if (seconds < 60) return 'A few seconds';
    if (seconds < 3600) return 'Minutes';
    if (seconds < 86400) return 'Hours';
    if (seconds < 86400 * 30) return 'Days';
    if (seconds < 86400 * 365) return 'Months';
    if (seconds < 86400 * 365 * 100) return 'Years';
    return 'Centuries';
  }
  // Score 0-100 and 4 levels: Very Weak, Could Be Better, Good, Strong.
  function rate(value) {
    const result = estimate(value);
    const score = Math.max(0, Math.min(100, Math.round(result.bits * 100 / 70)));
    const level = result.bits < 28 ? 0 : result.bits < 40 ? 1 : result.bits < 55 ? 2 : 3;
    return { ...result, score, level, time: timeText(result.seconds) };
  }
  window.passwordStrength = rate;
})();
