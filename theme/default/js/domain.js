(function () {
  // ---------------------------
  // Global Variables (IIFE scoped)
  // ---------------------------
  let passScore = 0;
  let improveScore = 0;
  let errorScore = 0;

  // The progress bar level (0 to 100)
  let progressLevel = 0;

  // The textual suffix to show near the overall score circle
  let scoreTxt = '%';

  // A handle to any "suggestion" box we might open
  let showSuggestionBox = '';

  // We have 13 modules in total
  const totalModules = 13;
  let modulesDone = 0;

  /**
   * We define an array of increments for each step so that they sum up to 100.
   * For example:
   * Steps 1..10 mostly get 6 or 8 points,
   * Steps 11 & 12 get 10,
   * Step 13 (final) gets enough to reach 100.
   *
   * Make sure the sum of all increments is exactly 100.
   */
  const stepIncrements = [
    6,  // Step 1:  Meta Data
    6,  // Step 2:  Headings
    6,  // Step 3:  Image Alt
    8,  // Step 4:  Key Cloud
    6,  // Step 5:  Page Analytics
    6,  // Step 6:  Text Ratio
    6,  // Step 7:  Social Card
    6,  // Step 8:  Social URL
    6,  // Step 9:  In-Page Links
    6,  // Step 10: Schema
    10, // Step 11: Server IP
    10, // Step 12: PageSpeed
    18  // Step 13: Final Clean Out => jump from ~82% to 100%
  ];

  // Keep track of which step we are on
  let currentStepIndex = 0;

  // Ensure a global object for pageSpeedReport exists (for the gauge)
  window.pageSpeedReport = window.pageSpeedReport || { 
    desktop: { score: 0 },
    mobile: { score: 0 }
  };

  // ---------------------------
  // Helper Functions
  // ---------------------------

  /**
   * Show or hide a suggestion box
   */
  function showSuggestion(sugBox) {
    showSuggestionBox = sugBox;
    $('#' + sugBox).slideToggle(100);
  }

  /**
   * A single function to increment the progress bar by a fixed amount,
   * and update the label with a "step name".
   */
  function updateProgressStep(stepName, incrementValue) {
    progressLevel += incrementValue;
    if (progressLevel > 100) progressLevel = 100;
    // Animate the progress bar to that new percentage
    $("#progressbar").animate({ width: progressLevel + "%" }, 500);
    $("#progress-label").html(stepName + " (" + progressLevel + "%)");
  }

  /**
   * Initialize the top 3 bar charts (pass/improve/error) to zero
   */
  function initialScore() {
    $("#passScore").css("width", passScore + '%');
    $("#improveScore").css("width", improveScore + '%');
    $("#errorScore").css("width", errorScore + '%');
  }

  /**
   * finalScore: Compute the overall score percentage and update the UI
   */
  function finalScore() {
    let totalPoints = passScore * 2 + improveScore; // pass => 2, improve => 1
    let checksCount = passScore + improveScore + errorScore;
    let maxPoints   = checksCount * 2;
    let overallPercent = (maxPoints > 0) ? Math.round((totalPoints / maxPoints) * 100) : 0;

    // For the bar chart segments:
    let pBar = (checksCount > 0) ? Math.round((passScore / checksCount) * 100) : 0;
    let iBar = (checksCount > 0) ? Math.round((improveScore / checksCount) * 100) : 0;
    let eBar = (checksCount > 0) ? Math.round((errorScore / checksCount) * 100) : 0;

    $("#passScore").css("width", pBar + '%').find(".scoreProgress-value").text(pBar + '%');
    $("#improveScore").css("width", iBar + '%').find(".scoreProgress-value").text(iBar + '%');
    $("#errorScore").css("width", eBar + '%').find(".scoreProgress-value").text(eBar + '%');

    // Update the big circle
    $('.second.circle').circleProgress({
      value: overallPercent / 100,
      animation: false
    });
    $("#overallscore").html(overallPercent + '<i class="newI">' + scoreTxt + '</i>');
  }

  /**
   * moduleDone: Called after each module’s HTML is inserted,
   * to update pass/improve/error aggregator and run finalScore
   */
  function moduleDone(scoreClass) {
    console.log("Module returned score class:", scoreClass);
    let lc = (scoreClass || "").toLowerCase();

    if (lc.indexOf('passedbox') > -1) {
      passScore += 3;
    } else if (lc.indexOf('improvebox') > -1) {
      improveScore += 3;
    } else if (lc.indexOf('errorbox') > -1) {
      errorScore += 3;
    }

    finalScore();

    modulesDone++;
    console.log("Updated scores:", { passScore, improveScore, errorScore, modulesDone });

    if (modulesDone === totalModules) {
      console.log("All modules completed => pass:", passScore, "improve:", improveScore, "error:", errorScore);
      // finalScore(); // optional extra call
    }
  }

  /**
   * initPageSpeedGauges: called after the PageSpeed data is loaded
   */
  function initPageSpeedGauges() {
    // Desktop gauge
    var desktopGauge = new Gauge({
      renderTo  : 'desktopPageSpeed',
      width     : 250,
      height    : 250,
      glow      : true,
      units     : 'Score',
      title     : 'Desktop',
      minValue  : 0,
      maxValue  : 100,
      majorTicks: ['0','20','40','60','80','100'],
      minorTicks: 5,
      strokeTicks: true,
      valueFormat: { int: 2, dec: 0, text: '%' },
      valueBox: { rectStart: '#888', rectEnd: '#666', background: '#CFCFCF' },
      valueText: { foreground: '#CFCFCF' },
      highlights: [
        { from: 0,  to: 40, color: '#EFEFEF' },
        { from: 40, to: 60, color: 'LightSalmon' },
        { from: 60, to: 80, color: 'Khaki' },
        { from: 80, to: 100, color: 'PaleGreen' }
      ],
      animation: { delay: 10, duration: 300, fn: 'bounce' }
    });
    desktopGauge.draw();

    // Mobile gauge
    var mobileGauge = new Gauge({
      renderTo  : 'mobilePageSpeed',
      width     : 250,
      height    : 250,
      glow      : true,
      units     : 'Score',
      title     : 'Mobile',
      minValue  : 0,
      maxValue  : 100,
      majorTicks: ['0','20','40','60','80','100'],
      minorTicks: 5,
      strokeTicks: true,
      valueFormat: { int: 2, dec: 0, text: '%' },
      valueBox: { rectStart: '#888', rectEnd: '#666', background: '#CFCFCF' },
      valueText: { foreground: '#CFCFCF' },
      highlights: [
        { from: 0,  to: 40, color: '#EFEFEF' },
        { from: 40, to: 60, color: 'LightSalmon' },
        { from: 60, to: 80, color: 'Khaki' },
        { from: 80, to: 100, color: 'PaleGreen' }
      ],
      animation: { delay: 10, duration: 300, fn: 'bounce' }
    });
    mobileGauge.draw();

    // Set the gauge values from window.pageSpeedReport if available
    var dsScore = parseInt(window.pageSpeedReport.desktop.score || '0', 10);
    var mbScore = parseInt(window.pageSpeedReport.mobile.score || '0', 10);

    desktopGauge.setValue(dsScore);
    mobileGauge.setValue(mbScore);
  }

  // Helper for AJAX
  function postAjax(params) {
    return new Promise((resolve, reject) => {
      $.post(domainPath, Object.assign({}, params, { hashcode: hashCode, url: inputHost }), function (data) {
        resolve(data);
      }).fail(function (err) {
        reject(err);
      });
    });
  }

  // The main chain of calls
  async function runAjaxCalls() {
    try {
      // STEP 1: Meta Data
      let data = await postAjax({ meta: '1', metaOut: '1', dId: domainId });
      let metaArr = data.split('!!!!8!!!!');
      $("#seoBox1").html(metaArr[0]);
      $("#seoBox2").html(metaArr[1]);
      $("#seoBox3").html(metaArr[2]);
      $("#seoBox5").html(metaArr[3]);
      updateProgressStep("Meta Data Processed", stepIncrements[currentStepIndex++]);
      let metaClass = $("#seoBox1").children().first().attr("class") || "";
      moduleDone(metaClass);

      

      // STEP 2: Heading Data
      data = await postAjax({ heading: '1', headingOut: '1', dId: domainId });
      $("#seoBox4").html(data);
      updateProgressStep("Headings Processed", stepIncrements[currentStepIndex++]);
      let headClass = $("#seoBox4").children().first().attr("class") || "";
      moduleDone(headClass);

      // STEP 3: Image Alt
      data = await postAjax({ image: '1', loaddom: '1', dId: domainId });
      $("#seoBox6").html(data);
      updateProgressStep("Image Alt Tags Processed", stepIncrements[currentStepIndex++]);
      let imgClass = $("#seoBox6").children().first().attr("class") || "";
      moduleDone(imgClass);

      // STEP 4: Keyword Cloud
      data = await postAjax({ keycloudAll: '1', meta: '1', heading: '1', dId: domainId });
      $("#seoBox8").html(data);
      updateProgressStep("Keyword Cloud & Consistency", stepIncrements[currentStepIndex++]);
      let keyCloudClass = $("#seoBox8").children().first().attr("class") || "";
      moduleDone(keyCloudClass);

    // STEP 5: Page Analytics
    data = await postAjax({ PageAnalytics: '1', dId: domainId });
    $("#seoBox54").html(data);
    updateProgressStep("Page Analytics Processed", stepIncrements[currentStepIndex++]);
    let pageAnalClass = $("#seoBox54").children().first().attr("class") || "";
    moduleDone(pageAnalClass);

      // STEP 6: Text-to-HTML Ratio
      data = await postAjax({ textRatio: '1', dId: domainId });
      $("#seoBox9").html(data);
      updateProgressStep("HTML Text Ratio Processed", stepIncrements[currentStepIndex++]);
      let textRatioClass = $("#seoBox9").children().first().attr("class") || "";
      moduleDone(textRatioClass);

      // STEP 7: Social Cards
      data = await postAjax({ sitecards: '1', dId: domainId });
      $("#seoBox51").html(data);
      updateProgressStep("Social Card Processed", stepIncrements[currentStepIndex++]);
      let socialCardClass = $("#seoBox51").children().first().attr("class") || "";
      moduleDone(socialCardClass);

      // STEP 8: Social URL
      data = await postAjax({ socialurls: '1', dId: domainId });
      $("#seoBox52").html(data);
      updateProgressStep("Social URL Processed", stepIncrements[currentStepIndex++]);
      let socialUrlClass = $("#seoBox52").children().first().attr("class") || "";
      moduleDone(socialUrlClass);

      // STEP 9: In-Page Links
      data = await postAjax({ linkanalysis: '1', loaddom: '1', inPageoutput: '1', dId: domainId });
      let linkArr = data.split('!!!!8!!!!');
      $("#seoBox13").html(linkArr[0]);
      $("#seoBox17").html(linkArr[1]);
      updateProgressStep("Link Analysis Processed", stepIncrements[currentStepIndex++]);
      let linkClass = $("#seoBox13").children().first().attr("class") || "";
      moduleDone(linkClass);

      // STEP 10: Schema Data
      data = await postAjax({ SchemaData: '1', dId: domainId });
      $("#seoBox44").html(data);
      updateProgressStep("Schema Data Processed", stepIncrements[currentStepIndex++]);
      let schemaClass = $("#seoBox44").children().first().attr("class") || "";
      moduleDone(schemaClass);

      // STEP 11: Server IP
      data = await postAjax({ serverIP: '1', dId: domainId });
      $("#seoBox36").html(data);
      updateProgressStep("Server IP Info Processed", stepIncrements[currentStepIndex++]);
      let serverIPClass = $("#seoBox36").children().first().attr("class") || "";
      moduleDone(serverIPClass);

      // STEP 12: PageSpeed Insights
      data = await postAjax({ PageSpeedInsights: '1', dId: domainId });
      $("#seoBox55").html(data);
      updateProgressStep("PageSpeed Insights Processed", stepIncrements[currentStepIndex++]);
      initPageSpeedGauges();
      let psClass = $("#seoBox55").children().first().attr("class") || "";
      moduleDone(psClass);

      // STEP 13: Final Clean Out
      await postAjax({
        cleanOut: '1',
        passscore: passScore,
        improvescore: improveScore,
        errorscore: errorScore,
        dId: domainId
      });
      updateProgressStep("Final Clean Out Done", stepIncrements[currentStepIndex++]);

      // Final summary
      finalScore();

      $("#debugOutput").text(
        "Pass: " + passScore +
        ", Improve: " + improveScore +
        ", Errors: " + errorScore +
        ", Overall: " + (
          (passScore + improveScore + errorScore)
            ? Math.round((passScore * 2 + improveScore) / ((passScore + improveScore + errorScore) * 2) * 100)
            : 0
        )
      );
      $('#progress-bar').fadeOut();

    } catch (error) {
      console.error("Error in AJAX chain:", error);
    }
  }

  // ---------------
  // Toggling Rows
  // ---------------
  $(document).on("click", "[class^='showMore']", function () {
    let className = $(this).attr("class");
    let match = className.match(/showMore([A-Za-z]+)/);
    if (match && match[1]) {
      toggleRows(match[1], true);
    }
    return false;
  });

  $(document).on("click", "[class^='showLess']", function () {
    let match = $(this).attr("class").match(/showLess([A-Za-z]+)/);
    if (match && match[1]) {
      toggleRows(match[1], false);
    }
    return false;
  });

  $(".seoBox").on("click", "a", function () {
    showSuggestion(showSuggestionBox);
  });

  $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    let targetPane = $(e.target).attr("href");
    $(targetPane).find("[class^='hideTr']").hide();
    $(targetPane).find("[class^='showLess']").hide();
    $(targetPane).find("[class^='showMore']").show();
  });

  function toggleRows(suffix, show) {
    const rows = $(`.hideTr${suffix}`);
    const showMoreBtn = $(`.showMore${suffix}`);
    const showLessBtn = $(`.showLess${suffix}`);
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

  // ---------------------------
  // Document Ready
  // ---------------------------
  $(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();

    // Preload screenshot (if you do this)
    $.get(domainPath + '?getImage&site=' + inputHost, function (data) {
      $("#screenshotData").html('<img src="data:image/jpeg;base64,' + data + '"/>');
    });
    
    // Start a small initial progress
    updateProgressStep("Initializing", 1);

    // Initialize the top bar segments to zero
    initialScore();

    // Possibly disable PDF link until complete
    $("a#pdfLink").attr("href", '#').prop("disabled", true);

    // Start the chain
    runAjaxCalls();
  });
})();
