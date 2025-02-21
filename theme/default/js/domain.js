// domains.js

(function () {
  // ---------------------------
  // Global Variables (IIFE scoped)
  // ---------------------------
  let passScore = 0;
  let improveScore = 0;
  let errorScore = 0;
  let progressLevel = 0;
  let scoreTxt = '%'; // Customize as needed
  let showSuggestionBox = '';

  // ---------------------------
  // Utility Functions
  // ---------------------------

  /**
   * Toggle the suggestion box.
   * @param {string} sugBox - The class name of the suggestion box to toggle.
   */
  function showSuggestion(sugBox) {
    showSuggestionBox = sugBox;
    $('.' + sugBox).slideToggle(100);
  }

  /**
   * Update the progress bar instantly.
   * @param {number} increment - The increment value.
   */
  function updateProgress(increment) {
    progressLevel += increment * 2;
    if (progressLevel > 100) progressLevel = 100;
    $("#progressbar").css("width", progressLevel + "%");
    $("#progress-label").html(progressLevel + "%");
  }

  /**
   * Animate the progress bar update while showing a step name.
   * For the "Social Data Processed" step, a slower animation speed is used.
   * @param {string} stepName - The name of the step.
   * @param {number} increment - The increment value.
   */
  function updateProgressStep(stepName, increment) {
    progressLevel += increment * 2;
    if (progressLevel > 100) progressLevel = 100;
    let animSpeed = (stepName === "Social Data Processed") ? 1000 : 500;
    $("#progressbar").animate({ width: progressLevel + "%" }, animSpeed);
    $("#progress-label").html(stepName + " (" + progressLevel + "%)");
  }

  /**
   * Initialize the score bars.
   */
  function initialScore() {
    $("#passScore").css("width", passScore + '%');
    $("#improveScore").css("width", improveScore + '%');
    $("#errorScore").css("width", errorScore + '%');
  }

  /**
   * Update the score based on the provided CSS class (passedBox, improveBox, errorBox).
   * Also updates the overall circle gauge and overall score text.
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

    // Update the circle gauge (using circleProgress plugin)
    $('.second.circle').circleProgress({
      value: passScore / 100,
      animation: false
    });
    $("#overallscore").html(passScore + '<i class="newI">' + scoreTxt + '</i>');
  }

  /**
   * Toggle rows for a given suffix.
   * This function handles both showing and hiding rows.
   * @param {string} suffix - The suffix for class names (e.g., "Trigrams", "Bigrams", "Unigrams").
   * @param {boolean} show - Whether to show (true) or hide (false) the rows.
   */
  function toggleRows(suffix, show) {
    console.log("Suffix:", suffix);
    const rows = $(`.hideTr${suffix}`);
    const showMoreBtn = $(`.showMore${suffix}`);
    const showLessBtn = $(`.showLess${suffix}`);
    console.log("Rows:", rows);
    console.log("Show More Button:", showMoreBtn);
    console.log("Show Less Button:", showLessBtn);

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
// Generic "Show More" handler using the toggleRows helper
$(document).on("click", "[class^='showMore']", function () {
  let className = $(this).attr("class");
  console.log("Button Class Name:", className);

  let match = className.match(/showMore([A-Za-z]+)/);
  console.log("Regex Match Result:", match);

  if (match && match[1]) {
      console.log("Extracted Suffix:", match[1]);
      toggleRows(match[1], true);
  } else {
      console.error("Failed to extract suffix from class name:", className);
  }
  return false;
});

$(document).on("click", "[class^='showLess']", function () {
  console.log("Show Less clicked");
  let match = $(this).attr("class").match(/showLess([A-Za-z]+)/);
  if (match && match[1]) {
      toggleRows(match[1], false);
  }
  return false;
});
  // ---------------------------
  // Event Handlers for Toggling Suggestion Boxes and Show More/Less
  // ---------------------------

  // Generic suggestion toggle (if any element within .seoBox is clicked)
  $(".seoBox").on("click", "a", function () {
    showSuggestion(showSuggestionBox);
  });

  // Generic "Show More" handler using the toggleRows helper
  $(document).on("click", "[class^='showMore']", function () {
    let match = $(this).attr("class").match(/showMore([A-Za-z]+)/);
    if (match && match[1]) {
      toggleRows(match[1], true);
    }
    return false;
  });

  // Generic "Show Less" handler using the toggleRows helper
  $(document).on("click", "[class^='showLess']", function () {
    let match = $(this).attr("class").match(/showLess([A-Za-z]+)/);
    if (match && match[1]) {
      toggleRows(match[1], false);
    }
    return false;
  });

  // ---------------------------
  // Bootstrap Tab Handling: Reset toggles when switching tabs.
  // ---------------------------
  $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    let targetPane = $(e.target).attr("href"); // e.g. "#trigrams-pane"
    $(targetPane).find("[class^='hideTr']").hide();
    $(targetPane).find("[class^='showLess']").hide();
    $(targetPane).find("[class^='showMore']").show();
  });

  // ---------------------------
  // Document Ready: Initialization and AJAX Calls
  // ---------------------------
  $(document).ready(function () {
    // Initialize tooltips.
    $('[data-toggle="tooltip"]').tooltip();

    // Load screenshot via AJAX GET.
    $.get(domainPath + '?getImage&site=' + inputHost, function (data) {
      $("#screenshotData").html('<img src="data:image/jpeg;base64,' + data + '"/>');
    });

    // Initialize progress and score bars.
    updateProgressStep("Initializing", 1);
    initialScore();
    $("a#pdfLink").attr("href", '#').prop("disabled", true);

    // Helper: Wrap $.post in a Promise.
    function postAjax(params) {
      return new Promise((resolve, reject) => {
        $.post(domainPath, Object.assign({}, params, { hashcode: hashCode, url: inputHost }), function (data) {
          resolve(data);
        }).fail(function (err) {
          console.error("AJAX call failed for params:", params, err);
          reject(err);
        });
      });
    }

    // Async function to run all AJAX calls sequentially.
    async function runAjaxCalls() {
      try {
        // 1. Meta Data Processing
        let data = await postAjax({ meta: '1', metaOut: '1' });
        let myArr = data.split('!!!!8!!!!');
        $("#seoBox1").html(myArr[0]);
        let cls = $("#seoBox1").children(":first").attr("class");
        if (cls) updateScore(cls);
        $("#seoBox2").html(myArr[1]);
        cls = $("#seoBox2").children(":first").attr("class");
        if (cls) updateScore(cls);
        $("#seoBox3").html(myArr[2]);
        $("#seoBox5").html(myArr[3]);
        updateProgressStep("Meta Data Processed", 5);

        // 2. Heading Data Processing
        data = await postAjax({ heading: '1', headingOut: '1' });
        $("#seoBox4").html(data);
        cls = $("#seoBox4").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("Headings Processed", 5);

        // 3. Image Alt Tags Processing
        data = await postAjax({ image: '1', loaddom: '1' });
        $("#seoBox6").html(data);
        cls = $("#seoBox6").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("Image Alt Tags Processed", 5);

        // Combined Keyword Cloud and Consistency Processing in a single AJAX call
        data = await postAjax({ keycloudAll: '1', meta: '1', heading: '1' });
        $("#seoBox8").html(data);
        cls = $("#seoBox8").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("Keyword Cloud & Consistency Processed", 10);

        // 6. Page Analysis Report Processing
        data = await postAjax({ PageAnalytics: '1' });
        $("#seoBox54").html(data);
        cls = $("#seoBox54").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("Page Analytics Processed", 5);

        // 6. Google PageSpeed Insights Processing
        data = await postAjax({ PageSpeedInsights: '1' });
        $("#seoBox55").html(data);
        cls = $("#seoBox55").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("PageSpeed Insights Processed", 5);

        // 6. Text-to-HTML Ratio Processing
        data = await postAjax({ textRatio: '1' });
        $("#seoBox9").html(data);
        cls = $("#seoBox9").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("HTML Text Ratio Processed", 5);

        // 6. Social Card Processing
        data = await postAjax({ sitecards: '1' });
        $("#seoBox51").html(data);
        cls = $("#seoBox51").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("Social Card Processed", 5);

        // 6. Social URL Processing
        data = await postAjax({ socialurls: '1' });
        $("#seoBox52").html(data);
        cls = $("#seoBox52").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("Social URL Processed", 5);

        // 7. In-Page Links Analysis
        data = await postAjax({ linkanalysis: '1', loaddom: '1', inPageoutput: '1' });
        let arr2 = data.split('!!!!8!!!!');
        $("#seoBox13").html(arr2[0]);
        cls = $("#seoBox13").children(":first").attr("class");
        if (cls) updateScore(cls);
        $("#seoBox17").html(arr2[1]);
        cls = $("#seoBox17").children(":first").attr("class");
        if (cls) updateScore(cls);
        updateProgressStep("Link Analysis Processed", 5);

        // 8. Server IP Information
        data = await postAjax({ serverIP: '1' });
        $("#seoBox36").html(data);
        updateProgressStep("Server IP Info Processed", 10);

        // 9. Schema Data Retrieval
        data = await postAjax({ SchemaData: '1' });
        $("#seoBox44").html(data);
        updateProgressStep("Social Data Processed", 5);

        // 10. Clean Out / Finalize Analysis
        await postAjax({
          cleanOut: '1',
          passscore: passScore,
          improvescore: improveScore,
          errorscore: errorScore
        });
        $('#progress-bar').fadeOut();
      } catch (error) {
        console.error("Error in AJAX chain:", error);
      }
    }

    // Start the AJAX call sequence.
    runAjaxCalls();
  });
})();
