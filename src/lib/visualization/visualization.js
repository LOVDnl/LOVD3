var create_visualization = function() {
  var dataset;
  var holder = document.getElementById('variants-visualization');

  if (holder === null) {
    return;
  }
  var transcript_id = holder.dataset.ncbi;
  var y_scale_type = "";

  var plot_data = function(data) {
    // Change these to fine-tune graph
    var domain_padding = 1000;
    var margin = { top: 20, right: 10, bottom: 20, left: 30 };
    var width = 1100 - margin.left - margin.right;
    var lane_height = 150;
    var lane_vertical_margin = 20;
    var exon_on_screen_percentage = 0.65;
    var minimum_width = 2;
    var tick_count = 80;
    var bold_tick_interval = 5;
    //////////////////////////////////

    var allowed_types = ["del", "subst", "dup", "delins", "ins", "inv", "con"];
    var type_to_label = {
      "del": "Deletions",
      "subst": "Substitutions",
      "dup": "Duplications",
      "delins": "Indels",
      "ins": "Insertions",
      "inv": "Inversions",
      "con": "Complex"
    }

    // *** UTIL FUNCTIONS *** //

    var calculate_height = function(lanes_count) {
      return lanes_count * (lane_height + 2 * lane_vertical_margin) + 100;
    }

    var lane_y_position = function(lane_index) {
      var lane_box_height = lane_height + 2 * lane_vertical_margin;
      var y = 0;

      for (var i = 0 ; i < lane_index ; i++) {
        y += lane_box_height;
      }

      return y + lane_box_height / 2;
    }

    var change_lane_y_position = function(lane_index) {
      var lane_box_height = lane_height + 2 * lane_vertical_margin;
      return lane_y_position(lane_index) + lane_box_height / 2;
    }

    var include = function(arr, obj) {
      return (arr.indexOf(obj) != -1);
    }

    // ********************** //

    var VariantsApp = {};
    VariantsApp.lanes_count = 2;
    VariantsApp.changes = {
      "del": [],
      "subst": [],
      "dup": [],
      "delins": [],
      "ins": [],
      "inv": [],
      "con": []
    }

    var height = calculate_height(VariantsApp.lanes_count);
    var exon_height = Math.round(lane_height * 0.75);
    var exon_in_utr_height = Math.round(exon_height * 0.5);
    var utr_height = Math.round(exon_height * 0.3);
    var change_height = utr_height;
    var regular_tick_height = Math.round(lane_height * 0.15);
    var bold_tick_height = Math.round(lane_height * 0.25);


    // *** EXTRACTING DOMAIN *** //

    var extract_domain = function(data) {
      var start = data.utr5.start;
      var stop = data.utr3.stop;

      VariantsApp.utr5 = [data.utr5.start, data.utr5.stop];
      VariantsApp.utr3 = [data.utr3.start, data.utr3.stop];

      return [start - domain_padding, stop + domain_padding];
    }

    var domain = extract_domain(data);
    VariantsApp.scale_start = domain[0];
    VariantsApp.scale_stop = domain[1];

    // ************************* //

    // *** SCALE *** //

    var create_regions = function(data) {
      VariantsApp.regions = [];

      var utr5_start = VariantsApp.utr5[0];
      var utr5_stop = VariantsApp.utr5[1];
      var utr3_start = VariantsApp.utr3[0];
      var utr3_stop = VariantsApp.utr3[1];

      VariantsApp.regions.push({ type: 'intron', start: VariantsApp.scale_start, stop: utr5_start });
      VariantsApp.regions.push({ type: 'utr', start: utr5_start, stop: utr5_stop });

      var exons_of_interest = [];

      for (var i = 0 ; i < data.exons.length ; i++) {
        var exon = data.exons[i];
        var exon_start = exon.start;
        var exon_stop = exon.stop;

        if (exon_stop <= utr5_stop || exon_start >= utr3_start) {
          // completely inside utr, we can skip it
        } else if (exon_start >= utr5_stop && exon_stop <= utr3_start) {
          // completely outside of utrs
          exons_of_interest.push([exon_start, exon_stop]);
        } else {
          // intersecting with utrs, we're interested in not intersecting part
          var not_intersecting_part_start;
          var not_intersecting_part_stop;

          if (exon_start < utr5_stop) {
            not_intersecting_part_start = utr5_stop;
            not_intersecting_part_stop = exon_stop;
          } else {
            not_intersecting_part_start = exon_start;
            not_intersecting_part_stop = utr3_start;
          }

          exons_of_interest.push([not_intersecting_part_start, not_intersecting_part_stop]);
        }
      }

      var last_part_stop = utr5_stop;

      for (var i = 0 ; i < exons_of_interest.length ; i++) {
        var exon = exons_of_interest[i];
        var exon_start = exon[0];
        var exon_stop = exon[1];

        if (last_part_stop < exon_start) {
          VariantsApp.regions.push({ type: 'intron', start: last_part_stop, stop: exon_start });
        }

        VariantsApp.regions.push({ type: 'exon', start: exon_start, stop: exon_stop });
        last_part_stop = exon_stop;
      }

      VariantsApp.regions.push({ type: 'utr', start: utr3_start, stop: utr3_stop });
      VariantsApp.regions.push({ type: 'intron', start: utr3_stop, stop: VariantsApp.scale_stop });
    }

    var calculate_scale_factors = function(data) {
      var scale_size = VariantsApp.scale_stop - VariantsApp.scale_start;

      create_regions(data);

      var sums = {
        'exon': 0,
        'utr': 0,
        'intron': 0
      };

      for (var i = 0 ; i < VariantsApp.regions.length ; i++) {
        var region = VariantsApp.regions[i];

        var length = region.stop - region.start;
        sums[region.type] += length;
      }

      var intron_scaling_factor = width * (1 - exon_on_screen_percentage) / (scale_size - sums['exon']);

      VariantsApp.scale_factors = {
        'exon': width * exon_on_screen_percentage / sums['exon'],
        'intron': intron_scaling_factor,
        'utr': intron_scaling_factor,
      }
    }

    calculate_scale_factors(data);

    var x_scale = function(x) {
      var passed_size = {
        'exon': 0,
        'intron': 0,
        'utr': 0
      };

      for (var i = 0 ; i < VariantsApp.regions.length ; i++) {
        var region = VariantsApp.regions[i];

        if (region.stop < x) {
          var length = region.stop - region.start;
          passed_size[region.type] += length;
        } else {
          var length = x - region.start;
          passed_size[region.type] += length;
          break;
        }
      }

      var result = 0;
      var types = ['exon', 'intron', 'utr'];
      for (var i = 0 ; i < types.length ; i++) {
        type = types[i];
        result += VariantsApp.scale_factors[type] * passed_size[type];
      }

      return result;
    }

    // ************* //

    // *** DRAWING *** //

    var svg = d3.select("#variants-visualization").append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
      .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    svg.append('defs')
      .append('pattern')
        .attr('id', 'diagonalHatch')
        .attr('patternUnits', 'userSpaceOnUse')
        .attr('width', 4)
        .attr('height', 4)
      .append('path')
        .attr('d', 'M-1,1 l2,-2 M0,4 l4,-4 M3,5 l2,-2')
        .attr('stroke', '#FDEF35')
        .attr('stroke-width', 1);

    var draw_rectangle = function(x, y, width, height, rect_class, url) {
      var parent_container = svg;

      if (url) {
        parent_container = parent_container.append("a")
              .attr("xlink:href", url)
              .attr("target", "_blank")
      }

      parent_container.append("rect")
          .attr("class", rect_class)
          .attr("x", x)
          .attr("y", y)
          .attr("width", width)
          .attr("height", height);
    }

    var draw_stripes = function(x, y, width, height, rect_class) {
      var parent_container = svg;

      parent_container.append("rect")
          .attr("class", rect_class)
          .attr("x", x)
          .attr("y", y)
          .attr("width", width)
          .attr("height", height)
          .style("fill", "url(#diagonalHatch)")
    }

    var plot_graph_element = function(element, lane_y, element_height, element_class, is_change) {
      var x1 = x_scale(element.start);
      var x2 = x_scale(element.stop);

      var scaled_width = x2 - x1;
      if (scaled_width < minimum_width) {
        scaled_width = minimum_width;
      }

      draw_rectangle(x1, lane_y - element_height / 2, scaled_width, element_height, element_class, false);
    }

    // Plot axes
    for (var i = 0 ; i < VariantsApp.lanes_count ; i++) {
      var y = lane_y_position(i);
      svg.append("line")
          .attr("x1", 0)
          .attr("y1", y)
          .attr("x2", width)
          .attr("y2", y);
    }

    var kFormatter = function(num) {
      return num > 999 ? (num/1000).toFixed(1) + 'k' : num;
    }


    var tick_lane_y = lane_y_position(0);

    var plot_ticks = function() {
      var domain_length = VariantsApp.utr3[1] - VariantsApp.utr5[0];
      var step = domain_length / tick_count;

      var start = VariantsApp.utr5[0];
      var scale_start = start;
      var top = 1;

      for (var i = 0 ; i < tick_count ; i++) {
        if (i % bold_tick_interval == 0 || i == tick_count - 1) {
          draw_rectangle(x_scale(start), tick_lane_y - bold_tick_height / 2, 2, bold_tick_height, 'tick', false);

          var y_translation = tick_lane_y + top * (bold_tick_height - 10);

          var text_value;
          if (i == 0 || i == tick_count -1) {
            text_value = Math.round(start);
          } else {
            text_value = "+" + kFormatter(Math.round(start - scale_start));
          }

          var text_box = svg.append("text")
            .attr("class", "main-axis-caption")
            .text(text_value);

          var text_box_width = text_box.node().getBBox().width;
          var x_translation = x_scale(start) - text_box_width / 2;

          text_box.attr("transform", "translate(" + x_translation + "," + y_translation + ")")

          top *= -1;
        } else {
          draw_rectangle(x_scale(start), tick_lane_y - regular_tick_height / 2, 1, regular_tick_height, 'tick', false);
        }
        start += step;
      }
    }

    plot_ticks();

    // Plot exons
    var exon_lane_y = lane_y_position(1);
    var utr5_stop = VariantsApp.utr5[1];
    var utr3_start = VariantsApp.utr3[0];

    var plot_exon = function(exon) {
      var start = exon.start;
      var stop = exon.stop;

      if (start >= utr5_stop && stop <= utr3_start) {
        // completely outside utrs
        plot_graph_element(exon, exon_lane_y, exon_height, "exon", false);
      } else if (stop <= utr5_stop || start >= utr3_start) {
        // completely inside utrs
      } else {
        // intersecting with utrs
        var rest = {};

        if (start < utr5_stop) {
          rest.start = utr5_stop;
          rest.stop = stop;
        } else {
          rest.start = start;
          rest.stop = utr3_start;
        }

        plot_graph_element(rest, exon_lane_y, exon_height, "exon", false);
      }
    }

    for (var i = 0 ; i < data.exons.length ; i++) {
      var exon = data.exons[i];
      plot_exon(exon);
    }

    // Plot utrs
    plot_graph_element(data.utr5, exon_lane_y, utr_height, "utr", false);
    plot_graph_element(data.utr3, exon_lane_y, utr_height, "utr", false);


    // Plot changes

    var extract_changes = function() {
      for (var i = 0 ; i < data.changes.length ; i++) {
        var change = data.changes[i];
        if (!include(allowed_types, change.type) || change.start == null || change.stop == null) {
          continue;
        }

        if (change.stop < VariantsApp.scale_start || change.start > VariantsApp.scale_stop) {
          continue;
        }

        var x = 0;
        if (change.start == change.stop) {
          x = 2;
        }

        VariantsApp.changes[change.type].push([change.start - x, change.stop + x, change.frameshift]);
      }
    }

    var draw_histogram = function(y_scale, start, end, height, y, type) {
      var x1 = x_scale(start);
      var x2 = x_scale(end);

      if (x1 < 0) {
        x1 = 0;
      }

      var scaled_width = x2 - x1;

      if (scaled_width < minimum_width) {
        scaled_width = minimum_width;
      }
      var scaled_height = 0;
      if (height > 0) {
        scaled_height = y_scale(height);
      }

      if (type == "frameshift") {
        draw_stripes(x1, y - scaled_height, scaled_width, scaled_height, type);
      } else {
        draw_rectangle(x1, y - scaled_height, scaled_width, scaled_height, type, false);
      }
    }

    var draw_changes = function() {
      for (var i = 0 ; i < allowed_types.length ; i++) {
        var type = allowed_types[i];

        if (VariantsApp.changes[type].length > 0) {
          // Add lane for change type
          lane_index = VariantsApp.lanes_count;
          VariantsApp.lanes_count = VariantsApp.lanes_count + 1;

          var new_height = calculate_height(VariantsApp.lanes_count);
          d3.select("#variants-visualization svg").attr("height", new_height);
          y = change_lane_y_position(lane_index);

          svg.append("line")
            .attr("x1", 0)
            .attr("y1", y)
            .attr("x2", width)
            .attr("y2", y);

          // Emit pairs
          var pairs = [];
          var changes_array = VariantsApp.changes[type];

          for (var j = 0 ; j < changes_array.length ; j++) {
            var change = changes_array[j];
            var frameshift = 0;

            var start = change[0];
            var end = change[1];

            if (start < VariantsApp.scale_start) {
              start = VariantsApp.scale_start;
            }

            if (end > VariantsApp.scale_stop) {
              end = VariantsApp.scale_stop;
            }

            if (change[2]) {
              frameshift = 1;
            }

            pairs.push([start, 1, frameshift]);
            pairs.push([end, -1, -1 * frameshift]);
          }

          pairs.sort(function(a, b) { return a[0] - b[0] });

          var h = 0;
          var frameshift_h = 0;
          var start = pairs[0][0];
          var frameshift_start = start;

          var histogram_queue = [];
          var stripes_queue = [];

          while (pairs.length > 0) {
            var current_pair = pairs.shift();

            var l = current_pair[0];
            var frameshift_l = current_pair[0];

            var h_l = h + current_pair[1];
            var frameshift_h_l = frameshift_h + current_pair[2];

            while (pairs.length > 0 && pairs[0][0] == l) {
              var next_pair = pairs.shift();
              h_l += next_pair[1];
              frameshift_h_l += next_pair[2];
            }

            if (h_l != h) {
              histogram_queue.push([start, l, h, y, type]);
              start = l;
            }

            if (frameshift_h_l != h) {
              stripes_queue.push([start, frameshift_l, frameshift_h, y])
              frameshift_start = frameshift_l;
            }

            h = h_l;
            frameshift_h = frameshift_h_l;
          }

          var max_h = d3.max(histogram_queue, function(d) { return d[2]; });
          if (max_h < 10) {
            max_h = 10;
          }

          if (y_scale_type == "lin") {
            var y_scale = d3.scaleLinear()
              .domain([0, max_h])
              .range([0, lane_height])
              .nice();

            var reversed_scale = d3.scaleLinear()
              .domain([max_h, 0])
              .range([0, lane_height])
              .nice();

            var scale_min = 0;
          } else {
            var y_scale = d3.scaleLog()
              .base(Math.E)
              .domain([1, max_h])
              .range([5, lane_height])
              .nice();

            var reversed_scale = d3.scaleLog()
              .base(Math.E)
              .domain([max_h, 1])
              .range([5, lane_height])
              .nice();

            var scale_min = 5;
          }

          var axis = d3.axisLeft(reversed_scale).tickFormat(d3.format(".0f"));

          svg.append("g")
            .attr("transform", "translate(0," + (y - lane_height - scale_min) + ")")
            .call(axis);

          svg.append("text")
            .attr("transform", "translate(" + width/2 + "," + (y - lane_height - scale_min) + ")")
            .text(type_to_label[type]);

          for (var k = 0 ; k < histogram_queue.length ; k++) {
            var values = histogram_queue[k];
            draw_histogram(y_scale, values[0], values[1], values[2], values[3], values[4]);
          }


          for (var k = 0 ; k < stripes_queue.length ; k++) {
            var values = stripes_queue[k];
            draw_histogram(y_scale, values[0], values[1], values[2], values[3], "frameshift");
          }
        }
      }
    }

    extract_changes();
    draw_changes();

    // *************** //
  }

  var load_data = function(transcript_id) {
    d3.json("/viz-get-variants.php?transcript=" + transcript_id, function(data) {
      plot_data(data);
    });
  }

  if (transcript_id && transcript_id.length > 0)  {
    load_data(transcript_id);
  }
}

create_visualization();
