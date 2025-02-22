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

  // Ensure that your PHP code outputs a global variable "window.pageSpeedReport"
  // containing the "report" object with desktop and mobile scores.
  window.pageSpeedReport = window.pageSpeedReport || { 
    desktop: { score: 0 },
    mobile: { score: 0 }
  };

  // ---------------------------
  // Utility Functions
  // ---------------------------

  function showSuggestion(sugBox) {
    showSuggestionBox = sugBox;
    $('#' + sugBox).slideToggle(100);
  }

  function updateProgress(increment) {
    progressLevel += increment * 2;
    if (progressLevel > 100) progressLevel = 100;
    $("#progressbar").css("width", progressLevel + "%");
    $("#progress-label").html(progressLevel + "%");
  }

  function updateProgressStep(stepName, increment) {
    progressLevel += increment * 2;
    if (progressLevel > 100) progressLevel = 100;
    let animSpeed = (stepName === "Social Data Processed") ? 1000 : 500;
    $("#progressbar").animate({ width: progressLevel + "%" }, animSpeed);
    $("#progress-label").html(stepName + " (" + progressLevel + "%)");
  }

  function initialScore() {
    $("#passScore").css("width", passScore + '%');
    $("#improveScore").css("width", improveScore + '%');
    $("#errorScore").css("width", errorScore + '%');
  }

  // ---------------------------
  // New finalScore() Function
  // ---------------------------
  function finalScore() {
    // Calculate percentages based on current scores.
    var pCount = parseInt(passScore, 10) || 0;
    var iCount = parseInt(improveScore, 10) || 0;
    var eCount = parseInt(errorScore, 10) || 0;
    
    var totalChecks = pCount + iCount + eCount;
    var pBar = totalChecks > 0 ? Math.round((pCount / totalChecks) * 100) : 0;
    var iBar = totalChecks > 0 ? Math.round((iCount / totalChecks) * 100) : 0;
    var eBar = totalChecks > 0 ? Math.round((eCount / totalChecks) * 100) : 0;
    
    // Update progress bars
    $("#passScore").css("width", pBar + '%').find(".scoreProgress-value").text(pBar + '%');
    $("#improveScore").css("width", iBar + '%').find(".scoreProgress-value").text(iBar + '%');
    $("#errorScore").css("width", eBar + '%').find(".scoreProgress-value").text(eBar + '%');
    
    // Use overallPercent (assumed updated elsewhere) to update the circle gauge.
    var finalP = parseInt(overallPercent, 10) || 0;
    $('.second.circle').circleProgress({
      value: finalP / 100,
      animation: false
    });
    $("#overallscore").html(finalP + '<i class="newI">' + scoreTxt + '</i>');
  }

  function initPageSpeedGauges() {
    // Initialize the Desktop Gauge
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
  
    // Initialize the Mobile Gauge
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
  
    // Get the scores from the global variable
    var desktopScore = parseInt(window.pageSpeedReport.desktop.score || '0', 10);
    var mobileScore  = parseInt(window.pageSpeedReport.mobile.score || '0', 10);
  
    desktopGauge.setValue(desktopScore);
    mobileGauge.setValue(mobileScore);
  }

  function updateScore(scoreClass) {
    scoreClass = scoreClass.toLowerCase();
    if (scoreClass === 'passedbox') {
      passScore += 3;
    } else if (scoreClass === 'improvebox') {
      improveScore += 3;
    } else {
      errorScore += 3;
    }
    
    // Update the visual progress bars.
    $("#passScore").css("width", passScore + '%');
    $("#improveScore").css("width", improveScore + '%');
    $("#errorScore").css("width", errorScore + '%');
  
    // Update the overall circle gauge using circleProgress plugin.
    $('.second.circle').circleProgress({
      value: passScore / 100,
      animation: false
    });
    
    $("#overallscore").html(passScore + '<i class="newI">' + scoreTxt + '</i>');
    console.log("Score updated:", scoreClass, passScore, improveScore, errorScore);
    
    // Update the global session report object
    window.seoReport = window.seoReport || {};
    window.seoReport.passScore = passScore;
    window.seoReport.improveScore = improveScore;
    window.seoReport.errorScore = errorScore;
    window.seoReport.overallScore = Math.round(passScore);
  }

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

  // ---------------------------
  // Document Ready: Initialization and AJAX Calls
  // ---------------------------
  $(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();

    $.get(domainPath + '?getImage&site=' + inputHost, function (data) {
      $("#screenshotData").html('<img src="data:image/jpeg;base64,' + data + '"/>');
    });
    
    updateProgressStep("Initializing", 1);
    initialScore();
    $("a#pdfLink").attr("href", '#').prop("disabled", true);

    function postAjax(params) {
      return new Promise((resolve, reject) => {
        $.post(domainPath, Object.assign({}, params, { hashcode: hashCode, url: inputHost }), function (data) {
          resolve(data);
        }).fail(function (err) {
          reject(err);
        });
      });
    }

    async function runAjaxCalls() {
      try {
        // 1. Meta Data Processing
        let data = await postAjax({ meta: '1', metaOut: '1' });
        let myArr = data.split('!!!!8!!!!');
        $("#seoBox1").html(myArr[0]);
        let firstChild = $("#seoBox1").children().first();
        if (firstChild.length) {
          let cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        $("#seoBox2").html(myArr[1]);
        firstChild = $("#seoBox2").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        $("#seoBox3").html(myArr[2]);
        $("#seoBox5").html(myArr[3]);
        updateProgressStep("Meta Data Processed", 5);

        // 2. Heading Data Processing
        data = await postAjax({ heading: '1', headingOut: '1' });
        $("#seoBox4").html(data);
        firstChild = $("#seoBox4").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("Headings Processed", 5);

        // 3. Image Alt Tags Processing
        data = await postAjax({ image: '1', loaddom: '1' });
        $("#seoBox6").html(data);
        firstChild = $("#seoBox6").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("Image Alt Tags Processed", 5);

        // 4. Combined Keyword Cloud and Consistency Processing
        data = await postAjax({ keycloudAll: '1', meta: '1', heading: '1' });
        $("#seoBox8").html(data);
        firstChild = $("#seoBox8").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("Keyword Cloud & Consistency Processed", 10);

        // 5. Page Analysis Report Processing
        data = await postAjax({ PageAnalytics: '1' });
        $("#seoBox54").html(data);
        firstChild = $("#seoBox54").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("Page Analytics Processed", 5);

        // 6. Google PageSpeed Insights Processing
        data = await postAjax({ PageSpeedInsights: '1' });
        $("#seoBox55").html(data);
        firstChild = $("#seoBox55").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("PageSpeed Insights Processed", 5);
        initPageSpeedGauges();

        // 7. Text-to-HTML Ratio Processing
        data = await postAjax({ textRatio: '1' });
        $("#seoBox9").html(data);
        firstChild = $("#seoBox9").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("HTML Text Ratio Processed", 5);

        // 8. Social Card Processing
        data = await postAjax({ sitecards: '1' });
        $("#seoBox51").html(data);
        firstChild = $("#seoBox51").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("Social Card Processed", 5);

        // 9. Social URL Processing
        data = await postAjax({ socialurls: '1' });
        $("#seoBox52").html(data);
        firstChild = $("#seoBox52").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("Social URL Processed", 5);

        // 10. In-Page Links Analysis
        data = await postAjax({ linkanalysis: '1', loaddom: '1', inPageoutput: '1' });
        let arr2 = data.split('!!!!8!!!!');
        $("#seoBox13").html(arr2[0]);
        firstChild = $("#seoBox13").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        $("#seoBox17").html(arr2[1]);
        firstChild = $("#seoBox17").children().first();
        if (firstChild.length) {
          cls = firstChild.attr("class");
          if (cls) updateScore(cls);
        }
        updateProgressStep("Link Analysis Processed", 5);

        // 11. Server IP Information
        data = await postAjax({ serverIP: '1' });
        $("#seoBox36").html(data);
        updateProgressStep("Server IP Info Processed", 10);

        // 12. Schema Data Retrieval
        data = await postAjax({ SchemaData: '1' });
        $("#seoBox44").html(data);
        updateProgressStep("Social Data Processed", 5);

        // 13. Clean Out / Finalize Analysis
        await postAjax({
          cleanOut: '1',
          passscore: passScore,
          improvescore: improveScore,
          errorscore: errorScore
        });
        // Then: fetch the final score from the DB.
        const finalScoreData = await postAjax({
          getFinalScore: '1',
          url: inputHost,
          hashcode: hashCode
        });

        console.log("Final Score:", finalScoreData);
        
        // Parse the finalScoreData (if it's a string, otherwise it may already be an object)
        let scoreObj = typeof finalScoreData === 'string' 
            ? JSON.parse(finalScoreData) 
            : finalScoreData;

        // Update your global score variables with the returned data
        passScore = scoreObj.passed;
        improveScore = scoreObj.improve;
        errorScore = scoreObj.errors;
        overallPercent = scoreObj.percent;

        // Now call finalScore() to update the DOM
        finalScore();

        // Optionally, update a debug element on the page (if you have one)
        $("#debugOutput").text(
          "Pass: " + passScore +
          ", Improve: " + improveScore +
          ", Errors: " + errorScore +
          ", Overall: " + overallPercent
        );

        $('#progress-bar').fadeOut();
      } catch (error) {
        console.error("Error in AJAX chain:", error);
      }
    }

    runAjaxCalls();
  });
})();
