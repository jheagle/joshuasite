/* main page custom scripts */
$(document).ready(function() {

	/* create slide effect on self promotion paragraphs */
	$(".promote div p, .promote div h4").slideUp(); // slide up first
	// toggle slide when control is clicked
	$(".promote-control .tab").click(function() {
		$(".promote div p, .promote div h4").stop().slideToggle();
	});
	$(".promote-control .tab").hover(function() {
		$(".promote div p, .promote div h4").stop().slideToggle();
	},function() {
	});
	
	/* load recent projects from json */
	$.getJSON('/db/projects.json', function(data) {
		$('.recent').empty(); // empty div first
		$('.recent').html($('.recent').html() + "<h2>I've been busy. . . .</h2>"); // add section heading
		$.each(data.projects, function(i, project) {
			$('.recent').html($('.recent').html() // for each project add it to the div
					+ "<div class='not-anchor'><p class='touch'>Tap Here</p><div class='project'><a href='"
					+ project.url + "' target='_self' title='" + project.title + "'><h3>"
					+ project.name + "</h3></a><a href='" + project.url + "' target='_self' title='"
					+ project.title + "'><h4>Created at " + project.location
					+ "</h4></a><div class='description'><a href='" + project.url + "' target='_self' title='"
					+ project.title + "'><p>" + project.description	+ "</p></a></div><img src='"
					+ project.img + "'  alt='" + project.alt + "' title='" + project.title + "'><div class='types'><a href='"
					+ project.url + "' target='_self' title='" + project.title + "'><p>" + project.type
					+ "</p></a></div></div></div>");
			if (screen.width <= 1024) { // if on mobile device, add tap button
				$(".not-anchor").bind('click', function() {
					$(".not-anchor").removeClass("clicked-anchor");
					$(this).addClass("clicked-anchor");
				});
			}
			if (i >= 5){ // only load top 5 (sorted by recent)
				return false;
			}
		});
	});
});