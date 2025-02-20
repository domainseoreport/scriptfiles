/**
 * dbdomain.js
 *
 * Handles the show/hide toggles for SEO analysis sections and
 * updates the final scoreboard bars and circle gauge.
 *
 * Global variables (defined server-side) that we rely on:
 *   passScore, improveScore, errorScore, scoreTxt
 *
 * Author: Your Name
 * Date: 2023-xx-xx
 */

var overScore = 0;             // Not used, but kept for compatibility
var showSuggestionBox = null;  // Tracks which suggestion box is currently open

/**
 * showSuggestion(sugBox)
 *
 * Slides the specified element (by ID) up/down.
 * @param {string} sugBox - The ID of the element to toggle (e.g. "seoBox8")
 *
 * NOTE: If your HTML says onclick="showSuggestion('seoBox8')",
 *       this function will do $('#seoBox8').slideToggle(100).
 */
function showSuggestion(sugBox) {
  showSuggestionBox = sugBox;
  // Toggle by ID instead of class:
  $('#' + sugBox).slideToggle(100);
}

/**
 * finalScore()
 *
 * Updates the scoreboard bars (Passed, To Improve, Errors) and
 * initializes the circle gauge for the overall score.
 *
 * Relies on global variables:
 *   passScore, improveScore, errorScore (set by the server-side),
 *   scoreTxt (the label for the circle gauge).
 */
function finalScore() {
  // Update individual progress bars
  $("#passScore").css("width", passScore + '%');
  $("#improveScore").css("width", improveScore + '%');
  $("#errorScore").css("width", errorScore + '%');

  // Use overallPercent for the circular gauge
  $('.second.circle').circleProgress({
    value: overallPercent / 100,
    animation: false
  });

  // Update overall score text with overallPercent instead of passScore
  $("#overallscore").html(overallPercent + '<i class="newI">' + scoreTxt + '</i>');
}

/*-------------------------------------------------------------------------
 | 1. HEADINGS (seoBox4) - Show/Hide More for .hideTr1
 *------------------------------------------------------------------------*/
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
  // Optional scroll
  var pos = $('.headingResult').offset();
  $('html, body').animate({ scrollTop: pos.top }, 800);
  return false;
});

/*-------------------------------------------------------------------------
 | 2. ALT ATTRIBUTE (seoBox6) - Show/Hide More for .hideTr2
 *------------------------------------------------------------------------*/
$("#seoBox6").on("click", ".showMore2", function (event) {
  event.stopPropagation(); // prevent parent click
  $(".hideTr2").fadeIn();
  $(".showMore2").hide();
  $(".showLess2").show();
  return false;
});

$("#seoBox6").on("click", ".showLess2", function (event) {
  event.stopPropagation(); // prevent parent click
  $(".hideTr2").fadeOut();
  $(".showLess2").hide();
  $(".showMore2").show();
  // Optionally, scroll back to the top of the section:
  var pos = $('.altImgResult').offset();
  $('html, body').animate({ scrollTop: pos.top }, 800);
  return false;
});

/*-------------------------------------------------------------------------
 | 3. KEYWORDS CLOUD (seoBox7) - Show/Hide More for .hideTr7
 *------------------------------------------------------------------------*/
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

/*-------------------------------------------------------------------------
 | 4. KEYWORD CONSISTENCY (seoBox8)
 |    We have separate tabs (Trigrams, Bigrams, Unigrams) with classes:
 |      .showMoreTrigrams, .showLessTrigrams, .hideTrTrigrams
 |      .showMoreBigrams,  .showLessBigrams,  .hideTrBigrams
 |      .showMoreUnigrams, .showLessUnigrams, .hideTrUnigrams
 *------------------------------------------------------------------------*/
$(document).on("click", ".showMoreTrigrams", function () {
  $(".hideTrTrigrams").fadeIn();
  $(".showMoreTrigrams").hide();
  $(".showLessTrigrams").show();
  return false;
});

$(document).on("click", ".showLessTrigrams", function () {
  $(".hideTrTrigrams").fadeOut();
  $(".showLessTrigrams").hide();
  $(".showMoreTrigrams").show();
  return false;
});

$(document).on("click", ".showMoreBigrams", function () {
  $(".hideTrBigrams").fadeIn();
  $(".showMoreBigrams").hide();
  $(".showLessBigrams").show();
  return false;
});

$(document).on("click", ".showLessBigrams", function () {
  $(".hideTrBigrams").fadeOut();
  $(".showLessBigrams").hide();
  $(".showMoreBigrams").show();
  return false;
});

$(document).on("click", ".showMoreUnigrams", function () {
  $(".hideTrUnigrams").fadeIn();
  $(".showMoreUnigrams").hide();
  $(".showLessUnigrams").show();
  return false;
});

$(document).on("click", ".showLessUnigrams", function () {
  $(".hideTrUnigrams").fadeOut();
  $(".showLessUnigrams").hide();
  $(".showMoreUnigrams").show();
  return false;
});

/*-------------------------------------------------------------------------
 | 5. IN-PAGE LINKS (seoBox13) - Show/Hide More for .hideTr4
 *------------------------------------------------------------------------*/
$("#seoBox13").on("click", ".showMore4", function () {
  $(".hideTr4").fadeIn();
  $(".showMore4").hide();
  $(".showLess4").show();
  return false;
});

$("#seoBox13").on("click", ".showLess4", function () {
  $(".hideTr4").fadeOut();
  $(".showLess4").hide();
  $(".showMore4").show();
  // Optional scroll
  var pos = $('.inPage').offset();
  $('html, body').animate({ scrollTop: pos.top }, 800);
  return false;
});

/*-------------------------------------------------------------------------
 | 6. BROKEN LINKS (seoBox14) - Show/Hide More for .hideTr5
 *------------------------------------------------------------------------*/
$("#seoBox14").on("click", ".showMore5", function () {
  $(".hideTr5").fadeIn();
  $(".showMore5").hide();
  $(".showLess5").show();
  return false;
});

$("#seoBox14").on("click", ".showLess5", function () {
  $(".hideTr5").fadeOut();
  $(".showLess5").hide();
  $(".showMore5").show();
  // Optional scroll
  var pos = $('.brokenLinks').offset();
  $('html, body').animate({ scrollTop: pos.top }, 800);
  return false;
});

/*-------------------------------------------------------------------------
 | 7. WHOIS (seoBox22) - Show/Hide More for .hideTr6
 *------------------------------------------------------------------------*/
$("#seoBox22").on("click", ".showMore6", function () {
  $(".hideTr6").fadeIn();
  $(".showMore6").hide();
  $(".showLess6").show();
  return false;
});

$("#seoBox22").on("click", ".showLess6", function () {
  $(".hideTr6").fadeOut();
  $(".showLess6").hide();
  $(".showMore6").show();
  // Optional scroll
  var pos = $('.whois').offset();
  $('html, body').animate({ scrollTop: pos.top }, 800);
  return false;
});

/*-------------------------------------------------------------------------
 | 8.Keyword Density (seoBox23) - Show/Hide More for .hideTr7
 *------------------------------------------------------------------------*/

 $(document).on("click", "[class^='showMore']", function (e) {
    e.preventDefault();
    // Extract suffix from the button class (capture everything after "showMore")
    let cls = $(this).attr("class");
    let match = cls.match(/showMore(\S+)/);
    if (match && match[1]) {
      let suffix = match[1]; // e.g., "1", "2", "Trigrams", etc.
      // Remove d-none, hide and fade in corresponding hidden rows
      $(".hideTr" + suffix).removeClass("d-none").hide().fadeIn();
      // Hide this button and show the corresponding "Show Less" button
      $(this).hide();
      $(".showLess" + suffix).removeClass("d-none").show();
    }
    return false;
  });
  
  $(document).on("click", "[class^='showLess']", function (e) {
    e.preventDefault();
    // Extract suffix from the button class (capture everything after "showLess")
    let cls = $(this).attr("class");
    let match = cls.match(/showLess(\S+)/);
    if (match && match[1]) {
      let suffix = match[1];
      // Fade out corresponding rows then add the d-none class
      $(".hideTr" + suffix).fadeOut(function() {
        $(this).addClass("d-none");
      });
      // Hide this button and show the corresponding "Show More" button
      $(this).hide();
      $(".showMore" + suffix).removeClass("d-none").show();
      // Optional: scroll to a specific section if needed (adjust selector as required)
      // $('html, body').animate({ scrollTop: $('.keyConsResult').offset().top }, 800);
    }
    return false;
  });

/*-------------------------------------------------------------------------
 | 8. Document Ready: Initialize finalScore
 *------------------------------------------------------------------------*/
$(document).ready(function () {
  finalScore();
});
