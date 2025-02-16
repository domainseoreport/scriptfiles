// domain.js

var passScore = 0;
var improveScore = 0;
var errorScore = 0;
var overScore = 0;
var showSuggestionBox = 0;
var progressLevel = 0;
var scoreTxt = '%'; // customize as needed

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

// --- Suggestion toggle for all seoBox elements ---
$(".seoBox").on("click", "a", function (event) {
    showSuggestion(showSuggestionBox);
});

// --- Updated Show More/Less Handlers ---
// Instead of fixed classes (.showMore3/.hideTr3) for keyword consistency,
// we use a dynamic handler that extracts the suffix from the class name.
$(document).on("click", "[class^='showMore']", function () {
    var match = $(this).attr("class").match(/showMore(.*)/);
    if (match && match[1]) {
        var suffix = match[1]; // e.g. "Trigrams", "Bigrams", or "Unigrams"
        $(".hideTr" + suffix).fadeIn();
        $(this).hide();
        $(".showLess" + suffix).show();
    }
    return false;
});
$(document).on("click", "[class^='showLess']", function () {
    var match = $(this).attr("class").match(/showLess(.*)/);
    if (match && match[1]) {
        var suffix = match[1];
        $(".hideTr" + suffix).fadeOut();
        $(this).hide();
        $(".showMore" + suffix).show();
        $('html,body').animate({ scrollTop: $('.keyConsResult').offset().top }, 800);
    }
    return false;
});

// --- Other Show More/Less Handlers ---
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
// (For tabs in the keyword consistency section)
// Ensure that your tab nav links use data-bs-toggle="tab" if you are on Bootstrap 5.
$('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    // When a new tab is activated, hide extra rows and reset toggles inside its pane.
    var targetPane = $(e.target).attr("href"); // e.g. "#trigrams-pane"
    $(targetPane).find("[class^='hideTr']").hide();
    $(targetPane).find("[class^='showLess']").hide();
    $(targetPane).find("[class^='showMore']").show();
});

// --- Document Ready Sequence ---
$(document).ready(function () {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Load screenshot via AJAX
    $.get(domainPath + '&getImage&site=' + inputHost, function (data) {
        $("#screenshotData").html('<img src="data:image/jpeg;base64,' + data + '"/>');
    });
    
    updateProgress(1);
    initialScore();
    $("a#pdfLink").attr("href", '#').prop("disabled", true);
    
    // Nested AJAX calls for each SEO section
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
                                            var arr2 = data.split('!!!!8!!!!');
                                            $("#seoBox13").html(arr2[0]);
                                            updateScore($("#seoBox13").children(":first").attr("class").toLowerCase());
                                            $("#seoBox17").html(arr2[1]);
                                            updateScore($("#seoBox17").children(":first").attr("class").toLowerCase());
                                            $("#seoBox18").html(arr2[2]);
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
                                                                var arr3 = data.split('!!!!8!!!!');
                                                                $("#seoBox21").html(arr3[0]);
                                                                $("#seoBox22").html(arr3[1]);
                                                                updateProgress(2);
                                                                
                                                                $.post(domainPath, { indexedPages: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                    $("#seoBox42").html(data);
                                                                    updateProgress(1);
                                                                    updateScore($("#seoBox42").children(":first").attr("class").toLowerCase());
                                                                    
                                                                    $.post(domainPath, { backlinks: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                        updateProgress(3);
                                                                        var arr4 = data.split('!!!!8!!!!');
                                                                        $("#seoBox43").html(arr4[0]);
                                                                        updateScore($("#seoBox43").children(":first").attr("class").toLowerCase());
                                                                        $("#seoBox45").html(arr4[1]);
                                                                        $("#seoBox46").html(arr4[2]);
                                                                        
                                                                        $.post(domainPath, { urlLength: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                            updateProgress(2);
                                                                            var arr5 = data.split('!!!!8!!!!');
                                                                            $("#seoBox26").html(arr5[0]);
                                                                            updateScore($("#seoBox26").children(":first").attr("class").toLowerCase());
                                                                            $("#seoBox27").html(arr5[1]);
                                                                            
                                                                            $.post(domainPath, { errorPage: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                $("#seoBox28").html(data);
                                                                                updateProgress(1);
                                                                                updateScore($("#seoBox28").children(":first").attr("class").toLowerCase());
                                                                                
                                                                                $.post(domainPath, { pageLoad: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                    updateProgress(3);
                                                                                    var arr6 = data.split('!!!!8!!!!');
                                                                                    $("#seoBox29").html(arr6[0]);
                                                                                    updateScore($("#seoBox29").children(":first").attr("class").toLowerCase());
                                                                                    $("#seoBox30").html(arr6[1]);
                                                                                    updateScore($("#seoBox30").children(":first").attr("class").toLowerCase());
                                                                                    $("#seoBox31").html(arr6[2]);
                                                                                    updateScore($("#seoBox31").children(":first").attr("class").toLowerCase());
                                                                                    
                                                                                    $.post(domainPath, { pageSpeedInsightChecker: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                        updateProgress(2);
                                                                                        var arr7 = data.split('!!!!8!!!!');
                                                                                        $("#seoBox48").html(arr7[0]);
                                                                                        $("#seoBox49").html(arr7[1]);
                                                                                        updateScore($("#seoBox48").children(":first").attr("class").toLowerCase());
                                                                                        updateScore($("#seoBox49").children(":first").attr("class").toLowerCase());
                                                                                        
                                                                                        $.post(domainPath, { availabilityChecker: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                            updateProgress(2);
                                                                                            var arr8 = data.split('!!!!8!!!!');
                                                                                            $("#seoBox32").html(arr8[0]);
                                                                                            $("#seoBox33").html(arr8[1]);
                                                                                            
                                                                                            $.post(domainPath, { emailPrivacy: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                updateProgress(1);
                                                                                                $("#seoBox34").html(data);
                                                                                                updateScore($("#seoBox34").children(":first").attr("class").toLowerCase());
                                                                                                
                                                                                                $.post(domainPath, { safeBrowsing: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                    $("#seoBox35").html(data);
                                                                                                    updateProgress(1);
                                                                                                    updateScore($("#seoBox35").children(":first").attr("class").toLowerCase());
                                                                                                    
                                                                                                    $.post(domainPath, { mobileCheck: '1', hashcode: hashCode, url: inputHost }, function (data) {
                                                                                                        var arr9 = data.split('!!!!8!!!!');
                                                                                                        $("#seoBox23").html(arr9[0]);
                                                                                                        updateScore($("#seoBox23").children(":first").attr("class").toLowerCase());
                                                                                                        $("#seoBox24").html(arr9[1]);
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
                                                                                                                        var arr10 = data.split('!!!!8!!!!');
                                                                                                                        $("#seoBox38").html(arr10[0]);
                                                                                                                        updateScore($("#seoBox38").children(":first").attr("class").toLowerCase());
                                                                                                                        $("#seoBox40").html(arr10[1]);
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
                                                                                                                                        
                                                                                                                                        // Clean out call
                                                                                                                                        $.post(domainPath, {
                                                                                                                                            cleanOut: '1',
                                                                                                                                            passscore: passScore,
                                                                                                                                            improvescore: improveScore,
                                                                                                                                            errorscore: errorScore,
                                                                                                                                            hashcode: hashCode,
                                                                                                                                            url: inputHost
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
