/* project search page custom scripts */
$(document).ready(function() {
	(function(){ // protect variables and functions from external interference
		var projects = $(); // save projects to prevent frequent callbacks
		var pagiArray = $(),
		pagiCurrent = 0;
		/* pre-load all projects so there is existing content */
		$.getJSON('/db/projects.json', function(data) {
			$(".results").empty(); // ensure div is empty first
			$(".results").append("All projects from most recent.");
			projects = data.projects; // add projects to reusable object
			/* load each project onto the page */
			var group = "";
			$.each(projects, function(i, project){	
				var p = (i % 10 === 0? "<hr />": "") + "<div class='result'><a href='"
						+ project.url + "'><img src='" + project.img + "' alt='"
						+ project.name + "'></a><div><a href='"	+ project.url
						+ "'><h3>" + project.name + "</h3><h4>Created at "
						+ project.location + "</h4><p>" + project.description
						+ "</p></a></div><p>" + project.type + "</p></div><hr />";	
				group += p;
				if((i+1) % 10 === 0){
					pagiArray.push(group);
					group = "";
				}
			});
			if(group.length > 0){
				pagiArray.push(group);				
			}
			pagiCurrent = 0;
			$(".results").append(pagiArray[pagiCurrent]);
			$(".pagination").empty();
			var x = 0;
			if(pagiCurrent > 6){
				x = pagiArray.length - pagiCurrent;
				if( x < 2){
					x = -7;
				} else if( x < 3){
					x = -6;
				} else if( x < 4){
					x = -5;
				} else if( x < 5){
					x = -4;
				} else if( x < 6){
					x = -3;
				} else if( x < 7){
					x = -2;
				} else if( x < 8){
					x = -1;
				} else{
					x = 0;
				}
			}
			var num  = 0 - pagiCurrent + 1;
			num = num > -7 && num < 7? num + x: -6 + x;
			for(var i = 0; i < 15 && i < pagiArray.length; i++){
				if(num === 1){
					$(".pagination").append("<a class='current' id='pagi-" + (pagiCurrent + num) + "' href='#'>" + (pagiCurrent + num) + "</a>");
				}else{
					$(".pagination").append("<a id='pagi-" + (pagiCurrent + num) + "' href='#'>" + (pagiCurrent + num) + "</a>");
				}
				++num;
			}
			if(pagiArray.length > 1){
				$(".pagination").append("<a id='pagi-next' href='#'>&rsaquo;</a><a id='pagi-last' href='#'>&#187;</a>");
			}
			$(".pagination").prepend("<p>Page " + (pagiCurrent + 1) + " of " + pagiArray.length + "</p>");
		});
		/* handle interactions with the input menus - perform immediate searches */
		$("#type").change(function (){ // modify search type if term exists
			if($("#search").val().trim() != "")
				doSearch($("#search").val(), $('#type').find(":selected").text());
		});	

		$("#search").keyup(function(e){ // type into the search box
			delay(function(){
				doSearch($("#search").val(), $('#type').find(":selected").text());				
			}, 600);
		});

		$("#searchBtn").click(function(e){ // click search button
			doSearch($("#search").val(), $('#type').find(":selected").text());
		});
		
		function pagination(){
			$(".results").html(pagiArray[pagiCurrent]);
			$(".pagination").empty();
			var x = 0;
			if(pagiCurrent > 6){
				x = pagiArray.length - pagiCurrent;
				if( x < 2){
					x = -7;
				} else if( x < 3){
					x = -6;
				} else if( x < 4){
					x = -5;
				} else if( x < 5){
					x = -4;
				} else if( x < 6){
					x = -3;
				} else if( x < 7){
					x = -2;
				} else if( x < 8){
					x = -1;
				} else{
					x = 0;
				}
			}
			var num  = 0 - pagiCurrent + 1;
			num = num > -7 && num < 7? num + x: -6 + x;
			for(var i = 0; i < 15 && i < pagiArray.length; i++){
				if(num === 1){
					$(".pagination").append("<a class='current' id='pagi-" + (pagiCurrent + num) + "' href='#'>" + (pagiCurrent + num) + "</a>");
				}else{
					$(".pagination").append("<a id='pagi-" + (pagiCurrent + num) + "' href='#'>" + (pagiCurrent + num) + "</a>");
				}
				++num;
			}
			if(pagiCurrent > 0){
				$(".pagination").prepend("<a id='pagi-first' href='#'>&#171;</a><a id='pagi-prev' href='#'>&lsaquo;</a>");
			}
			if(pagiCurrent < pagiArray.length - 1 && pagiArray.length > 1) {
				$(".pagination").append("<a id='pagi-next' href='#'>&rsaquo;</a><a id='pagi-last' href='#'>&#187;</a>");
			}
			$(".pagination").prepend("<p>Page " + (pagiCurrent + 1) + " of " + pagiArray.length + "</p>");
		}
		
		$(".pagination").on("click", "a",function(){
			var id = $(this).attr('id');
			id = id.replace('pagi-', '');
			if($.isNumeric(id)){
				pagiCurrent = parseInt(id)-1;
			}else{
				switch(id){
				case "first":
					pagiCurrent = 0;
					break;
				case "prev":
					pagiCurrent = pagiCurrent > 0? pagiCurrent - 1: 0;
					break;
				case "next":
					pagiCurrent = pagiCurrent < pagiArray.length? pagiCurrent + 1: pagiCurrent;					
					break;
				default:
					pagiCurrent = pagiArray.length - 1;
					break;
				}
			}
			pagination();
		});

		/* entry function for receiving input and sending it to filter, then displaying the input */
		function doSearch(term, type){
			type = type.toLowerCase() || "all"; // set type to lowercase if it is received
			$(".results").empty(); // ensure div is empty first
			var time = new Date().getTime(), // start time
			results = filter(projects, term, type); // request filtered object list
			time = (new Date().getTime() - time) / 1000; // calculate elapsed time
			$(".results").append(results.length + " Results for <strong>'" + term + "'</strong>"
					+ "<br>Returned in " + time + " seconds");
			var group = "",
			count = 0;
			pagiArray = $();
			$.each(results, function(i, result){
				if(result.value > 0){ // only display qualifying results
					var project = result.key,
					noMatch = result.noMatch,
					hasMatch = result.match,
					terms = term;
					
					/* display the keywords searched and found */
					$.each(noMatch, function(j, word){
						if(jQuery.inArray(word, hasMatch) < 0){
							var regEx = new RegExp(word, "ig");
							terms = terms.replace(regEx, '<del>$&</del>');
						}
					});
					
					/* print all the results with their associated information */
					var p = (count % 10 === 0? "<hr />": "") + "<p><strong>Match Score "
					+ result.value + "</strong></p><p>"
					+ terms + "</p><div class='result'><a href='"
					+ project.url + "'><img src='" + project.img + "' alt='"
					+ project.name + "'></a><div><a href='"	+ project.url
					+ "'><h3>" + project.name + "</h3><h4>Created at "
					+ project.location + "</h4><p>" + project.description
					+ "</p></a></div><p>" + project.type + "</p></div><hr />";
					group += p;
					if((count+1) % 10 === 0){
						pagiArray.push(group);
						group = "";
					}
					count++;
				}
			});
			if(group.length > 0){
				pagiArray.push(group);				
			}
			$(".pagination").empty();
			for(var i = 0; i < pagiArray.length; i++){
				$(".pagination").append("<a id='pagi-" + (i+1) + "' href='#'>" + (i+1) + "</a>&nbsp;");				
			}
			if(pagiArray.length > 1){
				$(".pagination").append("<a id='pagi-next' href='#'>Next</a>&nbsp;<a id='pagi-last' href='#'>Last</a>");
			}
			pagiCurrent = 0;
			$(".results").append(pagiArray[pagiCurrent]);
		}
		/* Delay function borrowed from Christian C. Salvadó (username CMS)
		 * from a StackOverflow post @ 
		 * http://stackoverflow.com/questions/1909441/jquery-keyup-delay */
		var delay = (function(){
		  var timer = 0;
		  return function(callback, ms){
		    clearTimeout (timer);
		    timer = setTimeout(callback, ms);
		  };
		})();
	})();
});

function filter(projects, term, type){
	/* create a new copy of the object list to modify, do not corrupt the original! */
	var data = jQuery.extend(true, {}, projects);
	type = type || "all";
	var results = [];
	if(term.length > 0){					
		var words = $.trim(term).split(" "); // split term into words
		
		switch(type){
		case "all":
			// if all, filter through to all searches
		case "name":
			$.each(data, function(i, project){
				var count = 0, // store project search rank
				noMatch = [], // store unmatched terms
				hasMatch = []; // store matched terms
				$.each(words, function(j, word){
					var num = 0; // score for current word
					
					word = escapeRegExp(word); // eliminate regex characters
					
					/* detect single letter input as first search, if it is then search
					 * all matching, otherwise search only complete words as case-insensitive */
					var regEx = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "ig"): new RegExp(word, "ig");
					
					var matches = project.name.match(regEx); // find matches
					num = (matches||[]).length; // add to temporary score
					if(num > 0){ // add a bonus if matches are case sensitive
						var regEx1 = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "g"): new RegExp(word, "g");
						matches = project.name.match(regEx1);
						num += (matches||[]).length;
						/* bold the words that do match in the object */
						project.name = project.name.replace(regEx, '<strong>$&</strong>');
					}
					
					if(num <= 0){
						/* add word to not matching if score is 0 */
						if(jQuery.inArray(word, noMatch) < 0){
							noMatch.push(word);
						}
						count -= Math.ceil(count / (j + 1)); // deduct from score
					}else {
						/* add word to matching if score is greater than 0 */
						if(jQuery.inArray(word, hasMatch) < 0){
							hasMatch.push(word);
						}
						/* increment score - value is rounded down */
						/* avoid floating point numbers by multiplying by
						 * 100 first, then divide by number of search terms */
						count += Math.floor((num * 100) / words.length);
					}								
				});
				/* if the not matching word list is larger than the matching, then serious deduction */
				if (noMatch.length > hasMatch.length){
					count = 0;
				}
				/* if there are previous results update the current results values */
				if (results.length > i){
					count += results[i].value;
					$.each(results[i].noMatch, function(x, item){
						if(jQuery.inArray(item, noMatch) < 0){
							noMatch.push(item);
						}
					});
					$.each(results[i].match, function(x, item){
						if(jQuery.inArray(item, hasMatch) < 0){
							hasMatch.push(item);
						}
					});
				}
				/* add these values to the results */
				results[i] = ({key : project, value: count, noMatch: noMatch, match: hasMatch});
			});	
			
			if(type === "name") break;
		case "type":
			$.each(data, function(i, project){
				var count = 0,
				noMatch = [],
				hasMatch = [],
				numTypes = project.type.split(/\s*,\s*/).length; // count the number of types associated with the project
				$.each(words, function(j, word){
					var num = 0;
					
					word = escapeRegExp(word);
					
					var regEx = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "ig"): new RegExp(word, "ig");
					
					var matches = project.type.match(regEx);
					num = (matches||[]).length;
					if(num > 0){
						var regEx1 = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "g"): new RegExp(word, "g");
						matches = project.type.match(regEx1);
						num += (matches||[]).length;
						project.type = project.type.replace(regEx, '<strong>$&</strong>');
					}
					
					if(num <= 0){
						if(jQuery.inArray(word, noMatch) < 0){
							noMatch.push(word);
						}
						count -= Math.ceil(count / (j + 1));
					}else{
						if(jQuery.inArray(word, hasMatch) < 0){
							hasMatch.push(word);
						}
						/* avoid floating point numbers by multiplying by
						 * 100 first, then divide by number of search terms.
						 * divide score by number of types, then boost the
						 * value by doubling it. */
						count += Math.floor((((num * 100) / words.length) / numTypes) * 2);
					}
				});
				if (noMatch.length > hasMatch.length){
					count = 0;
				}
				if (results.length > i){
					count += results[i].value;
					$.each(results[i].noMatch, function(x, item){
						if(jQuery.inArray(item, noMatch) < 0){
							noMatch.push(item);
						}
					});
					$.each(results[i].match, function(x, item){
						if(jQuery.inArray(item, hasMatch) < 0){
							hasMatch.push(item);
						}
					});
				}
				results[i] = ({key : project, value: count, noMatch: noMatch, match: hasMatch});
			});	
			
			if(type === "type") break;
		case "location":
			$.each(data, function(i, project){
				var count = 0,
				noMatch = [],
				hasMatch = [];
				$.each(words, function(j, word){
					var num = 0;
					
					word = escapeRegExp(word);
					
					var regEx = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "ig"): new RegExp(word, "ig");
					
					var matches = project.location.match(regEx);
					num = (matches||[]).length;
					if(num > 0){
						var regEx1 = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "g"): new RegExp(word, "g");
						matches = project.location.match(regEx1);
						num += (matches||[]).length;
						project.location = project.location.replace(regEx, '<strong>$&</strong>');
					}
					
					if(num <= 0){
						if(jQuery.inArray(word, noMatch) < 0){
							noMatch.push(word);
						}
						count -= Math.ceil(count / (j + 1));
					}else {
						if(jQuery.inArray(word, hasMatch) < 0){
							hasMatch.push(word);
						}
						/* avoid floating point numbers by multiplying by
						 * 100 first, then divide by number of search terms.
						 * boost this score by doubling it as location will
						 * receive few matches. */
						count += Math.floor(((num * 100) / words.length) * 2);
					}
				});
				if (noMatch.length > hasMatch.length){
					count = 0;
				}
				if (results.length > i){
					count += results[i].value;
					$.each(results[i].noMatch, function(x, item){
						if(jQuery.inArray(item, noMatch) < 0){
							noMatch.push(item);
						}
					});
					$.each(results[i].match, function(x, item){
						if(jQuery.inArray(item, hasMatch) < 0){
							hasMatch.push(item);
						}
					});
				}
				results[i] = ({key : project, value: count, noMatch: noMatch, match: hasMatch});
			});	
			
			if(type === "location") break;
		default: //description
			$.each(data, function(i, project){
				var count = 0,
				noMatch = [],
				hasMatch = [],
				perWord = [], // track score per word in this array
				hasAll = true, // if flag is true, bonus for full matching terms
				small = 0, // store smallest word score
				big = 0; // store largest word score
				$.each(words, function(j, word){
					var num = 0;
					
					word = escapeRegExp(word);
					
					var regEx = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "ig"): new RegExp(word, "ig");
					
					var matches = project.description.match(regEx);
					num = (matches||[]).length;
					if(num > 0){
						var regEx1 = word.length > 1 || words.length > 1? new RegExp("\\b" + word + "\\b", "g"): new RegExp(word, "g");
						matches = project.description.match(regEx1);
						num += (matches||[]).length;
						project.description = project.description.replace(regEx, '<strong>$&</strong>');
					}
					
					if(num <= 0){
						if(jQuery.inArray(word, noMatch) < 0){
							noMatch.push(word);
						}
						hasAll = false;
						count -= Math.ceil(count / (j + 1));
					}else{
						if(jQuery.inArray(word, hasMatch) < 0){
							hasMatch.push(word);
						}
						/* avoid floating point numbers by multiplying by
						 * 100 first, then divide by number of search terms.
						 * no other bonus as this could return a lot of
						 * results as-is */
						count += Math.floor((num * 100) / words.length);
						perWord.push(num);
					}
				});
				
				if (noMatch.length > hasMatch.length){
					count = 0;
				}else{
					/* find the smallest and largest word counts */
					for(var j = 0; j < perWord.length; j++){
						if(small === 0){
							small = perWord[j];
						}
						if(perWord[j] < small){
							small = perWord[j];
						}
						if(perWord[j] > big){
							big = perWord[j];
						}
					}
					
					if(count > 0){
						/* a small difference between word counts
						 * could mean that there is a close match */
						var x = count - big < big - small? count - big : big - small;
						
						count = count - x; // remove the over-bloated result from non-essential words (at, in, on, the)				
					}
					
					/* if all words were present, then change the
					 * bonus reflected by the result of more essential words */
					if(hasAll){
						count += small * 3;
					} else {
						count += small * 2;
					}
					
				}

				if (results.length > i){
					count += results[i].value;
					$.each(results[i].noMatch, function(x, item){
						if(jQuery.inArray(item, noMatch) < 0){
							noMatch.push(item);
						}
					});
					$.each(results[i].match, function(x, item){
						if(jQuery.inArray(item, hasMatch) < 0){
							hasMatch.push(item);
						}
					});
				}
				results[i] = ({key : project, value: count, noMatch: noMatch, match: hasMatch});
			});	
		}
		results = mergeSort(results); // sort results in order of score
	}
	return results;			
}

/* RegEx escape function borrowed from John Hunt from a StackOverflow post @
 * http://stackoverflow.com/questions/5306095/simple-jquery-javascript-method-to-escape-special-characters-in-string-for-reg */
function escapeRegExp(string){
	return string.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, "\\$1");
}

/* Merge sort algorithm borrowed and modified from
 * Nicholas C. Zakas @
 * http://www.nczonline.net/blog/2012/10/02/computer-science-and-javascript-merge-sort/
 * 
 * I knew I wanted to use a merge sort because of its speed and efficiency,
 * so I looked one up. Thanks to the first google result I found this
 * nice bit of code from Nicholas. While the extra memory usage may be a drawback,
 * it was easy to customize for my means and it is still a lovely algorithm.
 * This algorithm receives my search results and sorts them by score from highest to
 * lowest and removes all results of zero or less. */

/* mergeSort receives the results and splits them in half if they are greater than one
 * in length, each segment is passed to the merge function so they can be sorted
 * and returned recursivley. */
function mergeSort(items){
	// if length is less than two, then sorting is complete, return the item if it has a score of more than zero
    if (items.length < 2) {
    	items = items[0].value > 0? items : []; // check score
        return items;
    }

    var middle = Math.floor(items.length / 2), // find mid-point to cut
        left    = items.slice(0, middle), // segment on left
        right   = items.slice(middle), // segment on right
        params = merge(mergeSort(left), mergeSort(right)); // recursively split and merge segments
    
    params.unshift(0, items.length); // change all values in the array
    items.splice.apply(items, params); // replace items with new array
    return items;
}

/* combine results based on value */
function merge(left, right){
    var result  = [], // new result
        il      = 0, // incremental left counter
        ir      = 0; // incremental right counter
    /* perform merge while counters are within array index range */
    while (il < left.length && ir < right.length){
        if (left[il].value > right[ir].value){ // sort from high to low
            result.push(left[il++]); // add high value to the new results
        } else {
        	if(right[ir].value > 0) // check if low value is above zero, then add to the array
            	result.push(right[ir]);
        	ir++;
        }
    }
	
    // combine and return results
    return result.concat(left.slice(il)).concat(right.slice(ir));
}
