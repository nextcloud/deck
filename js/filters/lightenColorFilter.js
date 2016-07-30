app.filter('lightenColorFilter', function() {
	return function (hex) {
		var result = /^([A-Fa-f\d]{2})([A-Fa-f\d]{2})([A-Fa-f\d]{2})$/i.exec(hex);
		var color = result ? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16)
		} : null;
		if (result !== null) {
			var rgba = "rgba(" + color.r + "," + color.g + "," + color.b + ",0.7)";
			return rgba;
		} else {
			return "#" + hex;
		}
	}
});