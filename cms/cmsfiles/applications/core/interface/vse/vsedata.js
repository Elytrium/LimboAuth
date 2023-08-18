var ipsVSEData = {
	"sections": {
		"body": {
			"body": {
				"title": "Body",
				"settings": {
					"background": "page_background",
					"foreground": "text_color"
				}
            },
            "brand": {
                "title": "Primary brand",
                "settings": {
                    "background": "brand_primary"
                }
            },
			"lightText": {
				"title": "Light text",
				"settings": {
					"foreground": "text_light"
				}
			},
			"darkText": {
				"title": "Dark text",
				"settings": {
					"foreground": "text_dark"
				}
			},
			"link": {
				"title": "Link text",
				"settings": {
					"foreground": "link"
				}
			},
			"linkHover": {
				"title": "Link text hover",
				"settings": {
					"foreground": "link_hover"
				}
			},
			"footer": {
				"title": "Footer links text",
				"settings": {
					"foreground": "footer_text"
				}
			}
		},
		"header": {
			"appBar": {
				"title": "Main navigation",
				"settings": {
					"background": "main_nav",
					"foreground": "main_nav_font"
				}
			},
			"mainNavTab": {
				"title": "Active navigation tab",
				"settings": {
					"background": "main_nav_tab",
					"foreground": "main_nav_tab_font"
				}
			},
			"headerBar": {
				"title": "Header",
				"settings": {
					"background": "header",
					"foreground": "header_text"
				}
			},
			"mobileBack": {
				"title": "Mobile back button",
				"settings": {
					"background": "mobile_back",
					"foreground": "mobile_back_font"
				}
			},
		},
		"buttons": {
			"normalButton": {
				"title": "Normal button",
				"settings": {
					"background": "normal_button",
					"foreground": "normal_button_font"
				}
			},
			"primaryButton": {
				"title": "Primary button",
				"settings": {
					"background": "primary_button",
					"foreground": "primary_button_font"
				}
			},
			"importantButton": {
				"title": "Important button",
				"settings": {
					"background": "important_button",
					"foreground": "important_button_font"
				}
			},
			"lightButton": {
				"title": "Light button",
				"settings": {
					"background": "light_button",
					"foreground": "light_button_font"
				}
			},
			"veryLightButton": {
				"title": "Very light button",
				"settings": {
					"background": "very_light_button",
					"foreground": "very_light_button_font"
				}
			},
			"linkButton": {
				"title": "Link button",
				"settings": {
					"foreground": "link_button"
				}
			},
			"buttonBar": {
				"title": "Button Bar",
				"settings": {
					"background": "button_bar"
				}
			}
		},
		"backgrounds": {
			"areaBackground": {
				"title": "Area background",
				"settings": {
					"background": "area_background"
				}
			},
			"areaBackgroundLight": {
				"title": "Light area background",
				"settings": {
					"background": "area_background_light"
				}
			},
			"areaBackgroundReset": {
				"title": "Reset area background",
				"settings": {
					"background": "area_background_reset"
				}
			},
			"areaBackgroundDark": {
				"title": "Dark area background",
				"settings": {
					"background": "area_background_dark"
				}
			}
		},
		"other": {
			"itemStatus": {
				"title": "Item status badge",
				"settings": {
					"background": "item_status"
				}
			},
			"commentCount": {
				"title": "Comment count bubble",
				"settings": {
					"background": "comment_count",
					"foreground": "comment_count_font"
				}
			},
			"notification": {
				"title": "Notification bubble",
				"settings": {
					"background": "notification_bubble"
				}
			},
			"tabBar": {
				"title": "Tab bar background",
				"settings": {
					"background": "tab_background"
				}
			},
			"highlightedContent": {
				"title": "Highlighted posts",
				"settings": {
					"background": "post_highlight",
					"foreground": "post_highlight_border"
				}
			},
			"featuredRecommended": {
				"title": "Featured/recommended posts",
				"settings": {
					"background": "featured"
				}
			},
			"sectionTitle": {
				"title": "Section title bar",
				"settings": {
					"background": "section_title",
					"foreground": "section_title_font"
				}
			},
			"widgetTitleBar": {
				"title": "Widget title bar",
				"settings": {
					"background": "widget_title_bar",
					"foreground": "widget_title_font"
				}
			},
			"profileHeader": {
				"title": "Default profile header",
				"settings": {
					"background": "profile_header"
				}
			},
			"mentions": {
				"title": "Mentions",
				"settings": {
					"background": "mentions"
				}
			},
			"tags": {
				"title": "Tags",
				"settings": {
					"background": "tag",
					"foreground": "tag_font"
				}
			},
			"prefix": {
				"title": "Prefix",
				"settings": {
					"background": "prefix"
				}
			},
			"timeline": {
				"title": "Activity timeline",
				"settings": {
					"background": "timeline_color"
				}
			}			
		}
	}
};

var colorizer = {
	primaryColor: [
		'page_background',
		'brand_primary',
		'link',
		'link_hover',
		'main_nav',
		'main_nav_tab',
		'header',
		'header_text',
		'mobile_back',
		'normal_button',
		'primary_button',
		'important_button',
		'light_button',
		'light_button_font',
		'very_light_button',
		'very_light_button_font',
		'link_button',
		'area_background',
		'area_background_light',
		'area_background_reset',
		'area_background_dark',
		'item_status',
		'comment_count',
		'comment_count_font',
		'tab_background',
		'section_title',
		'widget_title_bar',
		'profile_header',
		'mentions',
		'prefix',
		'timeline_color',
	],
	secondaryColor: [
		'notification_bubble',
		'post_highlight',
		'post_highlight_border',
		'featured',
		'tag',
		'tag_font'
	],
	textColor: [
		'text_color',
		'text_light',
		'text_dark',
		'footer_text',
		'section_title_font',
		'widget_title_font'
	],
	startColors: {
		"primaryColor": "177ec9",
		"secondaryColor": "36ab7f",
		"textColor": "353c41"
	}
};