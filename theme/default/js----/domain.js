// domain.js

var passScore = 0;
var improveScore = 0;
var errorScore = 0;
var overScore = 0;
var showSuggestionBox = 0;
var progressLevel = 0;
var scoreTxt = '%'; // customize as needed

// ---------------- Utility Functions ----------------

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
    // Increase score based on the CSS class name (passedbox, improvebox, errorbox)
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

// ---------------- Show More/Less Handlers ----------------

// Headings (seoBox4) – scope within the container
$("#seoBox4").on("click", ".showMore1", function () {
    var $container = $(this).closest("#seoBox4");
    $container.find(".hideTr1").fadeIn();
    $container.find(".showMore1").hide();
    $container.find(".showLess1").show();
    return false;
});
$("#seoBox4").on("click", ".showLess1", function () {
    var $container = $(this).closest("#seoBox4");
    $container.find(".hideTr1").fadeOut();
    $container.find(".showLess1").hide();
    $container.find(".showMore1").show();
    $('html, body').animate({ scrollTop: $('.headingResult').offset().top }, 800);
    return false;
});

// Image Alt Tags (seoBox6)
$("#seoBox6").on("click", ".showMore2", function () {
    var $container = $(this).closest("#seoBox6");
    $container.find(".hideTr2").fadeIn();
    $container.find(".showMore2").hide();
    $container.find(".showLess2").show();
    return false;
});
$("#seoBox6").on("click", ".showLess2", function () {
    var $container = $(this).closest("#seoBox6");
    $container.find(".hideTr2").fadeOut();
    $container.find(".showLess2").hide();
    $container.find(".showMore2").show();
    $('html, body').animate({ scrollTop: $('.altImgResult').offset().top }, 800);
    return false;
});

// Keyword Consistency (seoBox8)
$("#seoBox8").on("click", ".showMore3", function () {
    var $container = $(this).closest("#seoBox8");
    $container.find(".hideTr3").fadeIn();
    $container.find(".showMore3").hide();
    $container.find(".showLess3").show();
    return false;
});
$("#seoBox8").on("click", ".showLess3", function () {
    var $container = $(this).closest("#seoBox8");
    $container.find(".hideTr3").fadeOut();
    $container.find(".showLess3").hide();
    $container.find(".showMore3").show();
    $('html, body').animate({ scrollTop: $('.keyConsResult').offset().top }, 800);
    return false;
});

// In-page Links (seoBox13)
$("#seoBox13").on("click", ".showMore4", function () {
    var $container = $(this).closest("#seoBox13");
    $container.find(".hideTr4").fadeIn();
    $container.find(".showMore4").hide();
    $container.find(".showLess4").show();
    return false;
});
$("#seoBox13").on("click", ".showLess4", function () {
    var $container = $(this).closest("#seoBox13");
    $container.find(".hideTr4").fadeOut();
    $container.find(".showLess4").hide();
    $container.find(".showMore4").show();
    $('html, body').animate({ scrollTop: $('.inPage').offset().top }, 800);
    return false;
});

// Broken Links (seoBox14)
$("#seoBox14").on("click", ".showMore5", function () {
    var $container = $(this).closest("#seoBox14");
    $container.find(".hideTr5").fadeIn();
    $container.find(".showMore5").hide();
    $container.find(".showLess5").show();
    return false;
});
$("#seoBox14").on("click", ".showLess5", function () {
    var $container = $(this).closest("#seoBox14");
    $container.find(".hideTr5").fadeOut();
    $container.find(".showLess5").hide();
    $container.find(".showMore5").show();
    $('html, body').animate({ scrollTop: $('.brokenLinks').offset().top }, 800);
    return false;
});

// WHOIS Data (seoBox22)
$("#seoBox22").on("click", ".showMore6", function () {
    var $container = $(this).closest("#seoBox22");
    $container.find(".hideTr6").fadeIn();
    $container.find(".showMore6").hide();
    $container.find(".showLess6").show();
    return false;
});
$("#seoBox22").on("click", ".showLess6", function () {
    var $container = $(this).closest("#seoBox22");
    $container.find(".hideTr6").fadeOut();
    $container.find(".showLess6").hide();
    $container.find(".showMore6").show();
    $('html, body').animate({ scrollTop: $('.whois').offset().top }, 800);
    return false;
});

// ---------------- Tab-Specific Toggles for N-Grams ----------------
// (Assume your PHP output for each n‑gram table uses unique classes)
// For Unigrams:
$(document).on("click", ".showMoreUnigrams", function () {
    var $container = $(this).closest(".tab-pane");
    $container.find(".hideTrUnigrams").fadeIn();
    $(this).hide();
    $container.find(".showLessUnigrams").show();
    return false;
});
$(document).on("click", ".showLessUnigrams", function () {
    var $container = $(this).closest(".tab-pane");
    $container.find(".hideTrUnigrams").fadeOut();
    $(this).hide();
    $container.find(".showMoreUnigrams").show();
    return false;
});
// For Bigrams:
$(document).on("click", ".showMoreBigrams", function () {
    var $container = $(this).closest(".tab-pane");
    $container.find(".hideTrBigrams").fadeIn();
    $(this).hide();
    $container.find(".showLessBigrams").show();
    return false;
});
$(document).on("click", ".showLessBigrams", function () {
    var $container = $(this).closest(".tab-pane");
    $container.find(".hideTrBigrams").fadeOut();
    $(this).hide();
    $container.find(".showMoreBigrams").show();
    return false;
});
// For Trigrams:
$(document).on("click", ".showMoreTrigrams", function () {
    var $container = $(this).closest(".tab-pane");
    $container.find(".hideTrTrigrams").fadeIn();
    $(this).hide();
    $container.find(".showLessTrigrams").show();
    return false;
});
$(document).on("click", ".showLessTrigrams", function () {
    var $container = $(this).closest(".tab-pane");
    $container.find(".hideTrTrigrams").fadeOut();
    $(this).hide();
    $container.find(".showMoreTrigrams").show();
    return false;
});

// ---------------- Tab Initialization ----------------
// When a tab is activated, reset its "Show More/Less" state and ensure its content is visible.
$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    var target = $(e.target).attr("href"); // e.g. "#trigrams-pane", "#bigrams-pane", or "#unigrams-pane"
    var $target = $(target);
    // Reset toggles inside the active tab:
    $target.find(".hideTr").hide();
    $target.find(".showLess").hide();
    $target.find(".showMore").show();
    // Also, for n-gram specific toggles, reset their rows:
    $target.find(".hideTrUnigrams, .hideTrBigrams, .hideTrTrigrams").hide();
    $target.find(".showLessUnigrams, .showLessBigrams, .showLessTrigrams").hide();
    $target.find(".showMoreUnigrams, .showMoreBigrams, .showMoreTrigrams").show();
    // Ensure the active tab is set to display block
    $target.css("display", "block");
});

// On document ready, trigger the active tab’s shown event so its content is visible.
$(document).ready(function () {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Load screenshot via AJAX
    $.get(domainPath + '&getImage&site=' + inputHost, function (data) {
        $("#screenshotData").html('<img src="data:image/jpeg;base64,' + data + '"/>');
    });

    updateProgress(1);
    initialScore();
    $("a#pdfLink").attr("href", '#');

    // Trigger the active tab's event (if using Bootstrap tabs)
    $('a[data-toggle="tab"].active').trigger('shown.bs.tab');

    // Chained AJAX calls to load SEO results (using your delimiter "!!!!8!!!!")
    $.post(domainPath, { meta: '1', metaOut: '1', hashcode: hashCode, url: inputHost }, function (data) {
        var myArr = data.split('!!!!8!!!!');
        updateProgress(5);
        $("#seoBox1").html(myArr[0]);
        updateScore($("#seoBox1").children(":first").attr("class").toLowerCase());
        $("#seoBox2").html(myArr[1]);
        updateScore($("#seoBox2").children(":first").attr("class").toLowerCase());
        $("#seoBox3").html(myArr[2]);
        $("#seoBox5").html(myArr[3]);
        $.post(domainPath, { heading: '1', headingOut: '1', hashcode: hashCode, url: inputHost }, function (data) {
            $("#seoBox4").html(data);
            updateProgress(1);
            updateScore($("#seoBox4").children(":first").attr("class").toLowerCase());
            $.post(domainPath, { image: '1', loaddom: '1', hashcode: hashCode, url: inputHost }, function (data) {
                $("#seoBox6").html(data);
                updateProgress(1);
                updateScore($("#seoBox6").children(":first").attr("class").toLowerCase());
                $.post(domainPath, { keycloud: '1', keycloudOut: '1', hashcode: hashCode, url: inputHost }, function (data) {
                    $("#seoBox7").html(data);
                    updateProgress(1);
                    $.post(domainPath, { keyConsistency: '1', meta: '1', heading: '1', keycloud: '1', hashcode: hashCode, url: inputHost }, function (data) {
                        $("#seoBox8").html(data);
                        updateProgress(1);
                        updateScore($("#seoBox8").children(":first").attr("class").toLowerCase());
                        $.post(domainPath, { textRatio: '1', hashcode: hashCode, url: inputHost }, function (data) {
                            $("#seoBox9").html(data);
                            updateProgress(1);
                            updateScore($("#seoBox9").children(":first").attr("class").toLowerCase());
                            $.post(domainPath, { gzip: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                $("#seoBox10").html(data);
                                updateProgress(1);
                                updateScore($("#seoBox10").children(":first").attr("class").toLowerCase());
                                $.post(domainPath, { www_resolve: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                    $("#seoBox11").html(data);
                                    updateProgress(1);
                                    updateScore($("#seoBox11").children(":first").attr("class").toLowerCase());
                                    $.post(domainPath, { ip_can: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                        $("#seoBox12").html(data);
                                        updateProgress(1);
                                        updateScore($("#seoBox12").children(":first").attr("class").toLowerCase());
                                        $.post(domainPath, { in_page: '1', loaddom: '1', inPageoutput: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                            updateProgress(3);
                                            var myArr = data.split('!!!!8!!!!');
                                            $("#seoBox13").html(myArr[0]);
                                            updateScore($("#seoBox13").children(":first").attr("class").toLowerCase());
                                            $("#seoBox17").html(myArr[1]);
                                            updateScore($("#seoBox17").children(":first").attr("class").toLowerCase());
                                            $("#seoBox18").html(myArr[2]);
                                            updateScore($("#seoBox18").children(":first").attr("class").toLowerCase());
                                            $.post(domainPath, { in_page: '1', loaddom: '1', brokenlinks: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                $("#seoBox14").html(data);
                                                updateProgress(1);
                                                updateScore($("#seoBox14").children(":first").attr("class").toLowerCase());
                                            });
                                            $.post(domainPath, { sitemap: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                $("#seoBox15").html(data);
                                                updateProgress(1);
                                                updateScore($("#seoBox15").children(":first").attr("class").toLowerCase());
                                                $.post(domainPath, { robot: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                    $("#seoBox16").html(data);
                                                    updateProgress(1);
                                                    updateScore($("#seoBox16").children(":first").attr("class").toLowerCase());
                                                    $.post(domainPath, { embedded: '1', loaddom: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                        $("#seoBox19").html(data);
                                                        updateProgress(1);
                                                        updateScore($("#seoBox19").children(":first").attr("class").toLowerCase());
                                                        $.post(domainPath, { iframe: '1', loaddom: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                            $("#seoBox20").html(data);
                                                            updateProgress(1);
                                                            updateScore($("#seoBox20").children(":first").attr("class").toLowerCase());
                                                            $.post(domainPath, { whois: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                var myArr = data.split('!!!!8!!!!');
                                                                $("#seoBox21").html(myArr[0]);
                                                                $("#seoBox22").html(myArr[1]);
                                                                updateProgress(2);
                                                                $.post(domainPath, { indexedPages: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                    $("#seoBox42").html(data);
                                                                    updateProgress(1);
                                                                    updateScore($("#seoBox42").children(":first").attr("class").toLowerCase());
                                                                    $.post(domainPath, { backlinks: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                        updateProgress(3);
                                                                        var myArr = data.split('!!!!8!!!!');
                                                                        $("#seoBox43").html(myArr[0]);
                                                                        updateScore($("#seoBox43").children(":first").attr("class").toLowerCase());
                                                                        $("#seoBox45").html(myArr[1]);
                                                                        $("#seoBox46").html(myArr[2]);
                                                                        $.post(domainPath, { urlLength: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                            updateProgress(2);
                                                                            var myArr = data.split('!!!!8!!!!');
                                                                            $("#seoBox26").html(myArr[0]);
                                                                            updateScore($("#seoBox26").children(":first").attr("class").toLowerCase());
                                                                            $("#seoBox27").html(myArr[1]);
                                                                            $.post(domainPath, { errorPage: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                $("#seoBox28").html(data);
                                                                                updateProgress(1);
                                                                                updateScore($("#seoBox28").children(":first").attr("class").toLowerCase());
                                                                                $.post(domainPath, { pageLoad: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                    updateProgress(3);
                                                                                    var myArr = data.split('!!!!8!!!!');
                                                                                    $("#seoBox29").html(myArr[0]);
                                                                                    updateScore($("#seoBox29").children(":first").attr("class").toLowerCase());
                                                                                    $("#seoBox30").html(myArr[1]);
                                                                                    updateScore($("#seoBox30").children(":first").attr("class").toLowerCase());
                                                                                    $("#seoBox31").html(myArr[2]);
                                                                                    updateScore($("#seoBox31").children(":first").attr("class").toLowerCase());
                                                                                    $.post(domainPath, { pageSpeedInsightChecker: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                        updateProgress(2);
                                                                                        var myArr = data.split('!!!!8!!!!');
                                                                                        $("#seoBox48").html(myArr[0]);
                                                                                        $("#seoBox49").html(myArr[1]);
                                                                                        updateScore($("#seoBox48").children(":first").attr("class").toLowerCase());
                                                                                        updateScore($("#seoBox49").children(":first").attr("class").toLowerCase());
                                                                                        $.post(domainPath, { availabilityChecker: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                            updateProgress(2);
                                                                                            var myArr = data.split('!!!!8!!!!');
                                                                                            $("#seoBox32").html(myArr[0]);
                                                                                            $("#seoBox33").html(myArr[1]);
                                                                                            $.post(domainPath, { emailPrivacy: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                updateProgress(1);
                                                                                                $("#seoBox34").html(data);
                                                                                                updateScore($("#seoBox34").children(":first").attr("class").toLowerCase());
                                                                                                $.post(domainPath, { safeBrowsing: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                    $("#seoBox35").html(data);
                                                                                                    updateProgress(1);
                                                                                                    updateScore($("#seoBox35").children(":first").attr("class").toLowerCase());
                                                                                                    $.post(domainPath, { mobileCheck: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                        var myArr = data.split('!!!!8!!!!');
                                                                                                        $("#seoBox23").html(myArr[0]);
                                                                                                        updateScore($("#seoBox23").children(":first").attr("class").toLowerCase());
                                                                                                        $("#seoBox24").html(myArr[1]);
                                                                                                        updateProgress(2);
                                                                                                        $.post(domainPath, { mobileCom: '1', loaddom: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                            $("#seoBox25").html(data);
                                                                                                            updateProgress(1);
                                                                                                            updateScore($("#seoBox25").children(":first").attr("class").toLowerCase());
                                                                                                            $.post(domainPath, { serverIP: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                $("#seoBox36").html(data);
                                                                                                                updateProgress(1);
                                                                                                                $.post(domainPath, { speedTips: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                    $("#seoBox37").html(data);
                                                                                                                    updateProgress(1);
                                                                                                                    updateScore($("#seoBox37").children(":first").attr("class").toLowerCase());
                                                                                                                    $.post(domainPath, { docType: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                        updateProgress(2);
                                                                                                                        var myArr = data.split('!!!!8!!!!');
                                                                                                                        $("#seoBox38").html(myArr[0]);
                                                                                                                        updateScore($("#seoBox38").children(":first").attr("class").toLowerCase());
                                                                                                                        $("#seoBox40").html(myArr[1]);
                                                                                                                        updateScore($("#seoBox40").children(":first").attr("class").toLowerCase());
                                                                                                                        $.post(domainPath, { w3c: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                            $("#seoBox39").html(data);
                                                                                                                            updateProgress(1);
                                                                                                                            $.post(domainPath, { encoding: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                                $("#seoBox41").html(data);
                                                                                                                                updateProgress(1);
                                                                                                                                updateScore($("#seoBox41").children(":first").attr("class").toLowerCase());
                                                                                                                                $.post(domainPath, { socialData: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                                    $("#seoBox44").html(data);
                                                                                                                                    updateProgress(1);
                                                                                                                                    $.post(domainPath, { visitorsData: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                                        updateProgress(100);
                                                                                                                                        $("#seoBox47").html(data);
                                                                                                                                        $("a#pdfLink").attr("href", pdfUrl);
                                                                                                                                        $('#pdfLink').unbind('click');
                                                                                                                                        $('#progress-bar').fadeOut();
                                                                                                                                        $.post(domainPath, { cleanOut: '1', passscore: passScore, improvescore: improveScore, errorscore: errorScore, hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                                                            // Clean-out callback
                                                                                                                                        });
                                                                                                                                    });
                                                                                                                                });
                                                                                                                            });
                                                                                                                        });
                                                                                                                    });
                                                                                                                });
                                                                                                            });
                                                                                                        });
                                                                                                    });
                                                                                                });
                                                                                            });
                                                                                        });
                                                                                    });
                                                                                });
                                                                            });
                                                                        });
                                                                    });
                                                                });
                                                            });
                                                        });
                                                    });
                                                });
                                            });
                                        });
                                    });
                                });
                            });
                        });
                    });
                });
            });
        });
    });
});
