(function() {
    // Global variables (scoped to the IIFE)
    let passScore = 0;
    let improveScore = 0;
    let errorScore = 0;
    let overScore = 0;
    let showSuggestionBox = 0;
    let progressLevel = 0;
    let scoreTxt = '%'; // customize as needed

    // --- Utility functions ---
    function showSuggestion(sugBox) {
        showSuggestionBox = sugBox;
        $('.' + sugBox).slideToggle(100);
    }

    function updateProgress(increment) {
        progressLevel += (increment * 2);
        if (progressLevel > 100) progressLevel = 100;
        $("#progressbar").css("width", progressLevel + "%");
        $("#progress-label").html(progressLevel + "%");
    }

    function initialScore() {
        $("#passScore").css("width", passScore + '%');
        $("#improveScore").css("width", improveScore + '%');
        $("#errorScore").css("width", errorScore + '%');
    }

    function updateScore(scoreClass) {
        // Increase score based on the CSS class name (passedBox, improveBox, errorBox)
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
    }

    // --- Event Handlers for Suggestion and Show More/Less ---
    $(".seoBox").on("click", "a", function () {
        showSuggestion(showSuggestionBox);
    });

    $(document).on("click", "[class^='showMore']", function () {
        let match = $(this).attr("class").match(/showMore(.*)/);
        if (match && match[1]) {
            let suffix = match[1]; // e.g. "Trigrams", "Bigrams", or "Unigrams"
            $(".hideTr" + suffix).fadeIn();
            $(this).hide();
            $(".showLess" + suffix).show();
        }
        return false;
    });

    $(document).on("click", "[class^='showLess']", function () {
        let match = $(this).attr("class").match(/showLess(.*)/);
        if (match && match[1]) {
            let suffix = match[1];
            $(".hideTr" + suffix).fadeOut();
            $(this).hide();
            $(".showMore" + suffix).show();
            $('html,body').animate({ scrollTop: $('.keyConsResult').offset().top }, 800);
        }
        return false;
    });

    // For headings (seoBox4)
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
        $('html,body').animate({ scrollTop: $('.headingResult').offset().top }, 800);
        return false;
    });

    // For image alt tags (seoBox6)
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
        $('html,body').animate({ scrollTop: $('.altImgResult').offset().top }, 800);
        return false;
    });

    // For in-page links (seoBox13)
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
        $('html,body').animate({ scrollTop: $('.inPage').offset().top }, 800);
        return false;
    });

    // For broken links (seoBox14)
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
        $('html,body').animate({ scrollTop: $('.brokenLinks').offset().top }, 800);
        return false;
    });

    // For WHOIS data (seoBox22)
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
        $('html,body').animate({ scrollTop: $('.whois').offset().top }, 800);
        return false;
    });

    // --- Bootstrap Tab Handling ---
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        let targetPane = $(e.target).attr("href"); // e.g. "#trigrams-pane"
        $(targetPane).find("[class^='hideTr']").hide();
        $(targetPane).find("[class^='showLess']").hide();
        $(targetPane).find("[class^='showMore']").show();
    });

    // --- Document Ready Sequence ---
    $(document).ready(function () {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Load screenshot via AJAX (GET)
        $.get(domainPath + '?getImage&site=' + inputHost, function (data) {
            $("#screenshotData").html('<img src="data:image/jpeg;base64,' + data + '"/>');
        });

        updateProgress(1);
        initialScore();
        $("a#pdfLink").attr("href", '#').prop("disabled", true);

        // Helper function to wrap $.post in a Promise with error handling
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

        // Async function to run all AJAX calls sequentially
        async function runAjaxCalls() {
            try {
                // --- Meta Data ---
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
                updateProgress(5);

                // --- Heading Data ---
                data = await postAjax({ heading: '1', headingOut: '1' });
                $("#seoBox4").html(data);
                cls = $("#seoBox4").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Image Alt Tags ---
                data = await postAjax({ image: '1', loaddom: '1' });
                $("#seoBox6").html(data);
                cls = $("#seoBox6").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Keyword Cloud ---
                data = await postAjax({ keycloud: '1', keycloudOut: '1' });
                $("#seoBox7").html(data);
                updateProgress(1);

                // --- Keyword Consistency ---
                data = await postAjax({ keyConsistency: '1', meta: '1', heading: '1', keycloud: '1' });
                $("#seoBox8").html(data);
                cls = $("#seoBox8").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Text-to-HTML Ratio ---
                data = await postAjax({ textRatio: '1' });
                $("#seoBox9").html(data);
                cls = $("#seoBox9").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- GZIP Compression Test ---
                data = await postAjax({ gzip: '1' });
                $("#seoBox10").html(data);
                cls = $("#seoBox10").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- WWW Resolve Check ---
                data = await postAjax({ www_resolve: '1' });
                $("#seoBox11").html(data);
                cls = $("#seoBox11").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- IP Canonicalization ---
                data = await postAjax({ ip_can: '1' });
                $("#seoBox12").html(data);
                cls = $("#seoBox12").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- In-Page Links Analyzer ---
                data = await postAjax({ in_page: '1', loaddom: '1', inPageoutput: '1' });
                let arr2 = data.split('!!!!8!!!!');
                $("#seoBox13").html(arr2[0]);
                cls = $("#seoBox13").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox17").html(arr2[1]);
                cls = $("#seoBox17").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox18").html(arr2[2]);
                cls = $("#seoBox18").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(3);

                // --- Broken Links Checker ---
                data = await postAjax({ in_page: '1', loaddom: '1', brokenlinks: '1' });
                $("#seoBox14").html(data);
                cls = $("#seoBox14").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Sitemap Checker ---
                data = await postAjax({ sitemap: '1' });
                $("#seoBox15").html(data);
                cls = $("#seoBox15").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Robots.txt Checker ---
                data = await postAjax({ robot: '1' });
                $("#seoBox16").html(data);
                cls = $("#seoBox16").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Embedded Objects Checker ---
                data = await postAjax({ embedded: '1', loaddom: '1' });
                $("#seoBox19").html(data);
                cls = $("#seoBox19").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- iFrame Checker ---
                data = await postAjax({ iframe: '1', loaddom: '1' });
                $("#seoBox20").html(data);
                cls = $("#seoBox20").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- WHOIS Data ---
                data = await postAjax({ whois: '1' });
                let arr3 = data.split('!!!!8!!!!');
                $("#seoBox21").html(arr3[0]);
                $("#seoBox22").html(arr3[1]);
                updateProgress(2);

                // --- Indexed Pages Counter ---
                data = await postAjax({ indexedPages: '1' });
                $("#seoBox42").html(data);
                cls = $("#seoBox42").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Backlink Counter / Alexa Rank / Site Worth ---
                data = await postAjax({ backlinks: '1' });
                let arr4 = data.split('!!!!8!!!!');
                $("#seoBox43").html(arr4[0]);
                cls = $("#seoBox43").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox45").html(arr4[1]);
                $("#seoBox46").html(arr4[2]);
                updateProgress(3);

                // --- URL Length & Favicon Checker ---
                data = await postAjax({ urlLength: '1' });
                let arr5 = data.split('!!!!8!!!!');
                $("#seoBox26").html(arr5[0]);
                cls = $("#seoBox26").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox27").html(arr5[1]);
                updateProgress(2);

                // --- Custom 404 Page Checker ---
                data = await postAjax({ errorPage: '1' });
                $("#seoBox28").html(data);
                cls = $("#seoBox28").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Page Load / Size / Language Checker ---
                data = await postAjax({ pageLoad: '1' });
                let arr6 = data.split('!!!!8!!!!');
                $("#seoBox29").html(arr6[0]);
                cls = $("#seoBox29").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox30").html(arr6[1]);
                cls = $("#seoBox30").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox31").html(arr6[2]);
                cls = $("#seoBox31").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(3);

                // --- Page Speed Insight Checker ---
                data = await postAjax({ pageSpeedInsightChecker: '1' });
                let arr7 = data.split('!!!!8!!!!');
                $("#seoBox48").html(arr7[0]);
                cls = $("#seoBox48").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox49").html(arr7[1]);
                cls = $("#seoBox49").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(2);

                // --- Domain & Typo Availability Checker ---
                data = await postAjax({ availabilityChecker: '1' });
                let arr8 = data.split('!!!!8!!!!');
                $("#seoBox32").html(arr8[0]);
                $("#seoBox33").html(arr8[1]);
                updateProgress(2);

                // --- Email Privacy Check ---
                data = await postAjax({ emailPrivacy: '1' });
                $("#seoBox34").html(data);
                cls = $("#seoBox34").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Safe Browsing Check ---
                data = await postAjax({ safeBrowsing: '1' });
                $("#seoBox35").html(data);
                cls = $("#seoBox35").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Mobile Friendliness Check ---
                data = await postAjax({ mobileCheck: '1' });
                let arr9 = data.split('!!!!8!!!!');
                $("#seoBox23").html(arr9[0]);
                cls = $("#seoBox23").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox24").html(arr9[1]);
                updateProgress(2);

                // --- Mobile Compatibility Checker ---
                data = await postAjax({ mobileCom: '1', loaddom: '1' });
                $("#seoBox25").html(data);
                cls = $("#seoBox25").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Server IP Information ---
                data = await postAjax({ serverIP: '1' });
                $("#seoBox36").html(data);
                updateProgress(1);

                // --- Speed Tips Analyzer ---
                data = await postAjax({ speedTips: '1' });
                $("#seoBox37").html(data);
                cls = $("#seoBox37").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Analytics & Document Type Checker ---
                data = await postAjax({ docType: '1' });
                let arr10 = data.split('!!!!8!!!!');
                $("#seoBox38").html(arr10[0]);
                cls = $("#seoBox38").children(":first").attr("class");
                if (cls) updateScore(cls);
                $("#seoBox40").html(arr10[1]);
                cls = $("#seoBox40").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(2);

                // --- W3C Validity Checker ---
                data = await postAjax({ w3c: '1' });
                $("#seoBox39").html(data);
                updateProgress(1);

                // --- Encoding Type Checker ---
                data = await postAjax({ encoding: '1' });
                $("#seoBox41").html(data);
                cls = $("#seoBox41").children(":first").attr("class");
                if (cls) updateScore(cls);
                updateProgress(1);

                // --- Social Data Retrieval ---
                data = await postAjax({ socialData: '1' });
                $("#seoBox44").html(data);
                updateProgress(1);

                // --- Visitors Localization ---
                data = await postAjax({ visitorsData: '1' });
                $("#seoBox47").html(data);
                updateProgress(100);
                $("a#pdfLink").attr("href", pdfUrl);
                $('#pdfLink').unbind('click');
                $('#progress-bar').fadeOut();

                // --- Clean Out / Finalize Analysis ---
                await postAjax({
                    cleanOut: '1',
                    passscore: passScore,
                    improvescore: improveScore,
                    errorscore: errorScore
                });
            } catch (error) {
                console.error("Error in AJAX chain:", error);
            }
        }

        // Start the AJAX chain
        runAjaxCalls();
    });
})();
