/**
 * dbdomain.js
 *
 * Handles the show/hide toggles for SEO analysis sections and
 * updates the final scoreboard bars, circle gauge, and PageSpeed gauges.
 *
 * Global variables (defined server-side) that we rely on:
 *   passScore, improveScore, errorScore, scoreTxt
 *
 * Author: Your Name
 * Date: 2023-xx-xx
 */

// Global score variables. If window.seoReport is set (via PHP), initialize from it.
var passScore = (window.seoReport && parseInt(window.seoReport.passScore, 10)) || 0;
var improveScore = (window.seoReport && parseInt(window.seoReport.improveScore, 10)) || 0;
var errorScore = (window.seoReport && parseInt(window.seoReport.errorScore, 10)) || 0;
var scoreTxt = typeof scoreTxt !== "undefined" ? scoreTxt : '%'; // Provided via PHP inline JS
var showSuggestionBox = null;  // Tracks which suggestion box is currently open

/**
 * showSuggestion(sugBox)
 *
 * Slides the specified element (by ID) up/down.
 * @param {string} sugBox - The ID of the element to toggle (e.g. "seoBox8")
 */
function showSuggestion(sugBox) {
  showSuggestionBox = sugBox;
  $('#' + sugBox).slideToggle(100);
}

/**
 * finalScore()
 *
 * Updates the scoreboard bars (Passed, To Improve, Errors) and
 * initializes the overall circle gauge.
 *
 * Relies on global variables: passScore, improveScore, errorScore, scoreTxt.
 */
function finalScore() {
  var pCount = parseInt(passScore, 10) || 0;
  var iCount = parseInt(improveScore, 10) || 0;
  var eCount = parseInt(errorScore, 10) || 0;
  
  var totalChecks = pCount + iCount + eCount;
  var pBar = totalChecks > 0 ? Math.round((pCount / totalChecks) * 100) : 0;
  var iBar = totalChecks > 0 ? Math.round((iCount / totalChecks) * 100) : 0;
  var eBar = totalChecks > 0 ? Math.round((eCount / totalChecks) * 100) : 0;
  
  $("#passScore").css("width", pBar + '%').find(".scoreProgress-value").text(pBar + '%');
  $("#improveScore").css("width", iBar + '%').find(".scoreProgress-value").text(iBar + '%');
  $("#errorScore").css("width", eBar + '%').find(".scoreProgress-value").text(eBar + '%');
  
  // overallPercent is updated via the AJAX call, now use it here
  var finalP = parseInt(overallPercent, 10) || 0;
  $('.second.circle').circleProgress({
    value: finalP / 100,
    animation: false
  });
  $("#overallscore").html(finalP + '<i class="newI">' + scoreTxt + '</i>');
}

/**
 * initPageSpeedGauges()
 *
 * Initializes the PageSpeed gauges (desktop and mobile) using the
 * scores from the global window.pageSpeedReport variable.
 */
function initPageSpeedGauges() {
  console.log("Initializing PageSpeed Gauges...");
  // Initialize the Desktop Gauge
  var desktopGauge = new Gauge({
    renderTo: 'desktopPageSpeed',
    width: 250,
    height: 250,
    glow: true,
    units: 'Score',
    title: 'Desktop',
    minValue: 0,
    maxValue: 100,
    majorTicks: ['0', '20', '40', '60', '80', '100'],
    minorTicks: 5,
    strokeTicks: true,
    valueFormat: { int: 2, dec: 0, text: '%' },
    valueBox: { rectStart: '#888', rectEnd: '#666', background: '#CFCFCF' },
    valueText: { foreground: '#CFCFCF' },
    highlights: [
      { from: 0, to: 40, color: '#EFEFEF' },
      { from: 40, to: 60, color: 'LightSalmon' },
      { from: 60, to: 80, color: 'Khaki' },
      { from: 80, to: 100, color: 'PaleGreen' }
    ],
    animation: { delay: 10, duration: 300, fn: 'bounce' }
  });
  desktopGauge.draw();

  // Initialize the Mobile Gauge
  var mobileGauge = new Gauge({
    renderTo: 'mobilePageSpeed',
    width: 250,
    height: 250,
    glow: true,
    units: 'Score',
    title: 'Mobile',
    minValue: 0,
    maxValue: 100,
    majorTicks: ['0', '20', '40', '60', '80', '100'],
    minorTicks: 5,
    strokeTicks: true,
    valueFormat: { int: 2, dec: 0, text: '%' },
    valueBox: { rectStart: '#888', rectEnd: '#666', background: '#CFCFCF' },
    valueText: { foreground: '#CFCFCF' },
    highlights: [
      { from: 0, to: 40, color: '#EFEFEF' },
      { from: 40, to: 60, color: 'LightSalmon' },
      { from: 60, to: 80, color: 'Khaki' },
      { from: 80, to: 100, color: 'PaleGreen' }
    ],
    animation: { delay: 10, duration: 300, fn: 'bounce' }
  });
  mobileGauge.draw();

  // Get the scores from the global variable (set by PHP)
  var desktopScore = parseInt(window.pageSpeedReport.desktop.score || '0', 10);
  var mobileScore = parseInt(window.pageSpeedReport.mobile.score || '0', 10);
  console.log("Desktop Score from global:", desktopScore);
  console.log("Mobile Score from global:", mobileScore);

  desktopGauge.setValue(desktopScore);
  mobileGauge.setValue(mobileScore);
}

/**
 * updateScore(scoreClass)
 *
 * Updates the score based on the CSS class passed (passedBox, improveBox, errorBox)
 * and updates the overall circle gauge.
 * @param {string} scoreClass - The CSS class indicating the score category.
 */
function updateScore(scoreClass) {
  scoreClass = scoreClass.toLowerCase();
  if (scoreClass === 'passedbox') {
    passScore += 3;
  } else if (scoreClass === 'improvebox') {
    improveScore += 3;
  } else {
    errorScore += 3;
  }
  $("#passScore").css("width", passScore + '%');
  $("#improveScore").css("width", improveScore + '%');
  $("#errorScore").css("width", errorScore + '%');

  $('.second.circle').circleProgress({
    value: passScore / 100,
    animation: false
  });
  $("#overallscore").html(passScore + '<i class="newI">' + scoreTxt + '</i>');
  console.log("Score updated:", scoreClass, passScore, improveScore, errorScore);
}

/**
 * toggleRows(suffix, show)
 *
 * Toggles the visibility of rows for a given suffix.
 * @param {string} suffix - The suffix used in class names.
 * @param {boolean} show - True to show, false to hide.
 */
function toggleRows(suffix, show) {
  console.log("Toggle rows for suffix:", suffix, "Show:", show);
  var rows = $(".hideTr" + suffix);
  var showMoreBtn = $(".showMore" + suffix);
  var showLessBtn = $(".showLess" + suffix);
  console.log("Rows found:", rows.length);
  if (show) {
    rows.removeClass("d-none").hide().fadeIn();
    showMoreBtn.hide();
    showLessBtn.removeClass("d-none").show();
  } else {
    rows.fadeOut(function () {
      $(this).addClass("d-none");
    });
    showLessBtn.hide();
    showMoreBtn.removeClass("d-none").show();
    $('html, body').animate({ scrollTop: $('.keyConsResult').offset().top }, 800);
  }
}

/* ----- Generic "Show More"/"Show Less" Handlers ----- */
$(document).on("click", "[class^='showMore']", function () {
  var cls = $(this).attr("class");
  console.log("Show More Button Class Name:", cls);
  var match = cls.match(/showMore(\S+)/);
  console.log("Regex Match Result:", match);
  if (match && match[1]) {
    toggleRows(match[1], true);
  } else {
    console.error("Failed to extract suffix from class name:", cls);
  }
  return false;
});

$(document).on("click", "[class^='showLess']", function () {
  var cls = $(this).attr("class");
  var match = cls.match(/showLess(\S+)/);
  if (match && match[1]) {
    toggleRows(match[1], false);
  }
  return false;
});

/* ----- Specific Handlers for Individual SEO Boxes ----- */
// Headings (seoBox4)
$("#seoBox4").on("click", ".showMore1", function () {
  $(".hideTr1").fadeIn();
  $(".showMore1").hide();
  $(".showLess1").show();
  return false;
});
$("#seoBox4").on("click", ".showLess1", function () {
  $(".hideTr1").fadeOut();
  $(".showLess1").hide();
  $(".showMore1").show();
  var pos = $('.headingResult').offset();
  $('html, body').animate({ scrollTop: pos.top }, 800);
  return false;
});

// Alt Attributes (seoBox6)
$("#seoBox6").on("click", ".showMore2", function (event) {
  event.stopPropagation();
  $(".hideTr2").fadeIn();
  $(".showMore2").hide();
  $(".showLess2").show();
  return false;
});
$("#seoBox6").on("click", ".showLess2", function (event) {
  event.stopPropagation();
  $(".hideTr2").fadeOut();
  $(".showLess2").hide();
  $(".showMore2").show();
  var pos = $('.altImgResult').offset();
  $('html, body').animate({ scrollTop: pos.top }, 800);
  return false;
});

// Keywords Cloud (seoBox7)
$("#seoBox7").on("click", ".showMore7", function () {
  $(".hideTr7").fadeIn();
  $(".showMore7").hide();
  $(".showLess7").show();
  return false;
});
$("#seoBox7").on("click", ".showLess7", function () {
  $(".hideTr7").fadeOut();
  $(".showLess7").hide();
  $(".showMore7").show();
  return false;
});

/* ----- Bootstrap Tab Handling: Reset toggles on tab switch ----- */
$('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
  var targetPane = $(e.target).attr("href");
  $(targetPane).find("[class^='hideTr']").hide();
  $(targetPane).find("[class^='showLess']").hide();
  $(targetPane).find("[class^='showMore']").show();
});

/* ----- Document Ready: Initialize finalScore and PageSpeed Gauges ----- */
$(document).ready(function () {
  finalScore();
  // If a global pageSpeedReport exists, initialize the PageSpeed gauges.
  if (window.pageSpeedReport && window.pageSpeedReport.desktop && window.pageSpeedReport.mobile) {
    initPageSpeedGauges();
  }
});


function showUpdateToast() {
  var toastEl = document.getElementById('updateToast');
  var toast = new bootstrap.Toast(toastEl, {delay: 5000});
  toast.show();
}

