(function () {
    // ---------------------------
    // Global Variables (IIFE scoped)
    // ---------------------------
    let passScore = 0;
    let improveScore = 0;
    let errorScore = 0;
    let overScore = 0;
    let showSuggestionBox = 0;
    let progressLevel = 0;
    let scoreTxt = '%'; // Customize as needed
  
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
      progressLevel += (increment * 2);
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
      progressLevel += (increment * 2);
      if (progressLevel > 100) progressLevel = 100;
      let animSpeed = 500;
      if (stepName === "Social Data Processed") {
        animSpeed = 1000;
      }
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
  
    // ---------------------------
    // Event Handlers for Toggling Suggestion Boxes and Show More/Less
    // ---------------------------
  
    // Generic suggestion toggle (if any element within .seoBox is clicked)
    $(".seoBox").on("click", "a", function () {
      showSuggestion(showSuggestionBox);
    });
  
// Generic "Show More" handler with d-none removal
$(document).on("click", "[class^='showMore']", function () {
    let match = $(this).attr("class").match(/showMore([A-Za-z]+)/);
    if (match && match[1]) {
      let suffix = match[1]; // e.g., "Trigrams"
      // Remove d-none and fade in the rows
      $(".hideTr" + suffix).removeClass("d-none").hide().fadeIn();
      // Hide this button
      $(this).hide();
      // Show the corresponding "Show Less" button
      $(".showLess" + suffix).removeClass("d-none").show();
    }
    return false;
  });
  
  // Generic "Show Less" handler with explicit .show() on "Show More"
  $(document).on("click", "[class^='showLess']", function () {
    let match = $(this).attr("class").match(/showLess([A-Za-z]+)/);
    if (match && match[1]) {
      let suffix = match[1]; // e.g., "Trigrams"
      // Fade out the rows and add d-none once hidden
      $(".hideTr" + suffix).fadeOut(function() {
        $(this).addClass("d-none");
      });
      // Hide this button
      $(this).hide();
      // Explicitly show the "Show More" button
      $(".showMore" + suffix).removeClass("d-none").show();
      // Optionally scroll back to the top of a specific section
      $('html, body').animate({ scrollTop: $('.keyConsResult').offset().top }, 800);
    }
    return false;
  });
  
  
    // Specific Handlers for various SEO boxes
    // Headings (seoBox4)
    $(document).on("click", ".showMore1", function () {
      $(".hideTr1").fadeIn();
      $(".showMore1").hide();
      $(".showLess1").show();
      return false;
    });
    $(document).on("click", ".showLess1", function () {
      $(".hideTr1").fadeOut();
      $(".showLess1").hide();
      $(".showMore1").show();
      $('html, body').animate({ scrollTop: $('.headingResult').offset().top }, 800);
      return false;
    });
  
    // Image Alt Tags (seoBox6)
    $(document).on("click", ".showMore2", function () {
      $(".hideTr2").fadeIn();
      $(".showMore2").hide();
      $(".showLess2").show();
      return false;
    });
    $(document).on("click", ".showLess2", function () {
      $(".hideTr2").fadeOut();
      $(".showLess2").hide();
      $(".showMore2").show();
      $('html, body').animate({ scrollTop: $('.altImgResult').offset().top }, 800);
      return false;
    });
  
    // In-Page Links (seoBox13)
    $(document).on("click", ".showMore4", function () {
      $(".hideTr4").fadeIn();
      $(".showMore4").hide();
      $(".showLess4").show();
      return false;
    });
    $(document).on("click", ".showLess4", function () {
      $(".hideTr4").fadeOut();
      $(".showLess4").hide();
      $(".showMore4").show();
      $('html, body').animate({ scrollTop: $('.inPage').offset().top }, 800);
      return false;
    });
  
    // Broken Links (seoBox14)
    $(document).on("click", ".showMore5", function () {
      $(".hideTr5").fadeIn();
      $(".showMore5").hide();
      $(".showLess5").show();
      return false;
    });
    $(document).on("click", ".showLess5", function () {
      $(".hideTr5").fadeOut();
      $(".showLess5").hide();
      $(".showMore5").show();
      $('html, body').animate({ scrollTop: $('.brokenLinks').offset().top }, 800);
      return false;
    });
  
    // WHOIS Data (seoBox22)
    $(document).on("click", ".showMore6", function () {
      $(".hideTr6").fadeIn();
      $(".showMore6").hide();
      $(".showLess6").show();
      return false;
    });
    $(document).on("click", ".showLess6", function () {
      $(".hideTr6").fadeOut();
      $(".showLess6").hide();
      $(".showMore6").show();
      $('html, body').animate({ scrollTop: $('.whois').offset().top }, 800);
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
  
      // Initialize progress.
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
  
          // 4. Keyword Cloud Processing
          data = await postAjax({ keycloud: '1', keycloudOut: '1' });
          $("#seoBox7").html(data);
          updateProgressStep("Keyword Cloud Processed", 5);
  
          // 5. Keyword Consistency Processing
          data = await postAjax({ keyConsistency: '1', meta: '1', heading: '1', keycloud: '1' });
          $("#seoBox8").html(data);
          cls = $("#seoBox8").children(":first").attr("class");
          if (cls) updateScore(cls);
          updateProgressStep("Keyword Consistency Processed", 5);

          // 6. Page Analysis Report Processing
          data = await postAjax({ PageAnalytics: '1' }); 
          $("#seoBox54").html(data);
          cls = $("#seoBox54").children(":first").attr("class");
          if (cls) updateScore(cls);
          updateProgressStep("Page Analytics Processed", 5);
          
  
          // 6. Text-to-HTML Ratio Processing
          data = await postAjax({ textRatio: '1' });
          $("#seoBox9").html(data);
          cls = $("#seoBox9").children(":first").attr("class");
          if (cls) updateScore(cls);
          updateProgressStep("HTML Text Ratio Processed", 5);

          // 6. Show Cards Processing
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
  