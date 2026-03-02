const translations = {
    'en': {
        // Header
        'Dashboard': 'Dashboard',
        'Mold': 'Mold',
        'Tufting': 'Tufting',
        'Tuft': 'Tuft',
        'Blister': 'Blister',
        'Online': 'Online',
        'Warning': 'Warning',
        'Offline': 'Offline',
        'Total': 'Total',
        'Flexible': 'Flexible',
        'Action': 'Action',
        'System Settings': 'System Settings',
        'Login': 'Login',
        'Logout': 'Logout',
        'Monitoring': 'Monitoring',
        'Location Management': 'Location Management',
        'Monitoring Dashboard': 'Monitoring Dashboard',
        'Add New Device': 'Add New Device',
        
        // Device Status
        'Devices online': 'Devices online',
        'Devices breached thresholds': 'Devices breached thresholds',
        'Devices disconnected': 'Devices disconnected',
        'Devices total': 'Devices total',
        'Action total': 'Action total',
        
        // Modal Information
        'Information': 'Information',
        'ID': 'ID',
        'Process': 'Process',
        'Mold Cavity': 'Mold Cavity',
        'Actual Cavity': 'Actual Cavity',
        'Capacity': 'Capacity',
        'Efficiency': 'Efficiency',
        'Eff.requirement': 'Eff.requirement',
        'Current Cycle': 'Current Cycle',
        'Target': 'Target',
        'Upper Limit': 'Upper Limit',
        'Lower Limit': 'Lower Limit',
        'Total lost pcs': 'Total lost pcs',
        'Lost time': 'Lost time',
        'Total Count': 'Total Count',
        
        // Chart and Controls
        'Hourly Efficiency': 'Hourly Efficiency',
        '2 day ago': '2 day ago',
        'Yesterday': 'Yesterday',
        'Today': 'Today',
        'From dateline': 'From dateline',
        'To dateline': 'To dateline',
        'SEARCH': 'SEARCH',
        'RESET': 'RESET',
        'Total Output': 'Total Output',
        'Average Cycle': 'Average Cycle',
        'Export to Excel': 'Export to Excel',
        
        // Loss Report
        'Loss & Idle Time Report': 'Loss & Idle Time Report',
        
        // Action Modal
        '+ Add Action': '+ Add Action',
        'Create new Action': 'Create new Action',
        'Issue ID': 'Issue ID',
        'Issue Description': 'Issue Description',
        'Issue Type': 'Issue Type',
        'Actual Efficiency': 'Actual Efficiency',
        'Actual Cycle': 'Actual Cycle',
        'Efficiency Required': 'Efficiency Required',
        'Priority': 'Priority',
        'Due': 'Due',
        'Creator': 'Creator',
        'Action Plans': 'Action Plans',
        '+ Add Plan': '+ Add Plan',
        'Action ID': 'Action ID',
        'Planned completion date': 'Planned completion date',
        'Owner': 'Owner',
        'Cancel': 'Cancel',
        'Create Action': 'Create Action',
        
        // Priority levels
        'low': 'Low',
        'medium': 'Medium',
        'high': 'High',
        'urgent': 'Urgent',
        
        // Common
        'Close': 'Close',
        'Save': 'Save',
        'Delete': 'Delete',
        'Edit': 'Edit',
        'View': 'View',
        'NO DATA': 'NO DATA',
        'BACK': 'BACK',
        'VIEW MORE': 'VIEW MORE',
        'pcs_per_minute': 'Pcs/minute',
        'rpm': 'RPM',
        'brushes_per_cycle': 'Brushes/cycle',
        'pcs': 'pcs',
        'Shift Insert': 'Shift Insert',
        'Manual Insert': 'Manual Insert',
        'Rotating': 'Rotating',
        'Shot': 'Shot',
        'hole/brush': 'Hole/Brush',
        'family': 'Family',
        'chiller': 'Chiller',
        'vacuum tank': 'Vacuum Tank',
        'compressor': 'Compressor',
        'end air pressure': 'End Air Pressure',
        'air tank': 'Air Tank',
        'workshop temperature': 'Workshop Temperature',
        
        // Tabs
        'Issue': 'Issue',
        'Cooling Tower': 'Cooling Tower',
        'Chiller': 'Chiller',
        'Vacuum Tank': 'Vacuum Tank',
        'Air Dryer': 'Air Dryer',
        'Compressor': 'Compressor',
        'End Air Pressure': 'End Air Pressure',
        'Air Tank': 'Air Tank',
        'Air Conditioner': 'Air Conditioner',
        'Workshop Temperature': 'Workshop Temperature',
        
        // Table Headers
        'System': 'System',
        'Device ID': 'Device ID',
        'Location': 'Location',
        'Time': 'Time',
        'Total Count (h)': 'Total Count (h)',
        'Status': 'Status',
        'Connection': 'Connection',
        'Temperature': 'Temperature',
        'Humidity': 'Humidity',
        'Pressure': 'Pressure',
        'Actual': 'Actual',
        'Lower': 'Lower',
        'Upper': 'Upper',
        'Add': 'Add',
        'Mold ID (Unique)': 'Mold ID (Unique)',
        'Family': 'Family',
        'Model': 'Model',
        'Manufacturer': 'Manufacturer',
        'Mfg. Date': 'Mfg. Date',
        'Capacity (1h)': 'Capacity (1h)',
        'Unit': 'Unit',
        'Mold Type': 'Mold Type',
        'Efficiency Limit(%)': 'Efficiency Limit(%)',
        'Limits (s) (LL/TG/UL)': 'Limits (s) (LL/TG/UL)',
        'Fre. Check Connected (s)': 'Fre. Check Connected (s)',
        'Fre. Check Limit (s)': 'Fre. Check Limit (s)',
        'Actions': 'Actions',
        'Injection ID': 'Injection ID',
        'Frequency Check Connected (s)': 'Frequency Check Connected (s)',
        'Frequency Check Limit (s)': 'Frequency Check Limit (s)',
        'Tufting ID': 'Tufting ID',
        'Flex': 'Flex',
        'Hole/Brush': 'Hole/Brush',
        'Limits (pcs) (LL/TG/UL)': 'Limits (pcs) (LL/TG/UL)',
        'End-rounding ID': 'End-rounding ID',
        'Blister ID': 'Blister ID',
        'Brushes/Cycle': 'Brushes/Cycle',
        'Limits (cycles) (LL/TG/UL)': 'Limits (cycles) (LL/TG/UL)',
        'Injection': 'Injection',
        'End-rounding': 'End-rounding',
        'Manufacturing Date': 'Manufacturing Date',
        'Manual': 'Manual',
        'Auto': 'Auto',
        'Number of Cavities': 'Number of Cavities',
        'Machine Type': 'Machine Type',
        'Lower Limit (s)': 'Lower Limit (s)',
        'Target (s)': 'Target (s)',
        'Upper Limit (s)': 'Upper Limit (s)',
        
        // Pagination
        'Previous': 'Previous',
        'Next': 'Next',
        'Page': 'Page',
        'of': 'of',
        
        // Modal
        'All tabs': 'All tabs',
        'Search tab...': 'Search tab...',
        
        // Tooltips
        'Device is Disconnected': 'Device is Disconnected',
        'No issues detected': 'No issues detected',
        'No data available': 'No data available',
        
        // New translations for JavaScript strings
        'DateTime': 'DateTime',
        'Cavities': 'Cavities',
        'CycleTime': 'Cycle Time (s)',
        'Output': 'Output (pcs)',
        'NoDataToExport': 'No data available to export. Please perform a search first.',
        'ErrorUnknownDeviceType': 'Unknown device type for export.',
        'FailedToExport': 'Failed to export data.',
        'SelectBothDates': 'Please select both "From" and "To" dates.',
        'BrushesPerCycle': 'Brushes/Cycle',
        'CycleCount': 'Cycle Count',
        'OutputPcsMin': 'Output (pcs/min)',
        'NoDataStructure': 'No data structure available',
        'AverageOutput': 'Average Output',
        'Cycle': 'Cycle',
        'lost_pcs': 'Lost pcs(pcs)',
        'idle_breakdown': 'Idle/Breakdown(s)',
        'AverageCycle': 'Average Cycle',
        'Select': 'Select',
        'Type': 'Type',
        'In progress': 'In progress',
        'Complete': 'Complete',
        'Description Of Issue': 'Description Of Issue',
        'Created Date': 'Created Date',
        'Planned Completion Date': 'Planned Completion Date',
        'Machine': 'Machine',
        'Open': 'Open',
        'Issue': 'Issue',
        'Action plan': 'Action plan',
        'Created by': 'Created by',
        'Created': 'Created',
        'Action Status': 'Action Status',
        'Approval': 'Approval',
        'Manage': 'Manage',
        'Create': 'Create',
        
        // Additional translations from previous JSON
        'EMS Dashboard': 'EMS Dashboard',
        'Overall': 'Overall',
        'Running': 'Running',
        'Break Down': 'Break Down',
        '07:00 ~ 19:00': '07:00 ~ 19:00',
        '19:00 ~ 07:00': '19:00 ~ 07:00',
        'Average': 'Average',
        'Molding': 'Molding',
        'Blistering': 'Blistering',
        'Machine ID': 'Machine ID',
        'Capacity / hr': 'Capacity / hr',
        'EMS Monitoring': 'EMS Monitoring',
        'Machine Details': 'Machine Details',
        'Efficiency Report': 'Efficiency Report',
        'Output Report': 'Output Report',
        'Output Summary': 'Output Summary',
        'Output (pcs)': 'Output (pcs)',
        'Lost (pcs)': 'Lost (pcs)',
        'All Families': 'All Families',
        'SubTotal': 'SubTotal',
        'No data': 'No data',
        'Loading...': 'Loading...',
        'No data found for this filter.': 'No data found for this filter.',
        'Failed to load data. Please try again.': 'Failed to load data. Please try again.',
        'Failed to load families:': 'Failed to load families:',
        'Error loading': 'Error loading',
        
        // Previous new translations
        'Mold ID': 'Mold ID',
        'Details': 'Details',
        'Report': 'Report',
        'From (VN)': 'From (VN)',
        'To (VN)': 'To (VN)',
        
        // New translation added
        'Approve Actions': 'Approve Actions',
        
        // Previous new translations added
        'Update Location': 'Update Location',
        'Location Name': 'Location Name',
        'Description': 'Description',
        'Add New Location': 'Add New Location',
        
        // Previous new translations added
        'Welcome': 'Welcome',
        'Data': 'Data',
        'Frequency': 'Frequency',
        'Check limit': 'Check limit',
        'Temp (°C)': 'Temp (°C)',
        'Humidity (%)': 'Humidity (%)',
        'Pressure (kg/cm²)': 'Pressure (kg/cm²)',
        
        // Previous new translations added
        'Frequency (seconds)': 'Frequency (seconds)',
        'System Type': 'System Type',
        'Update Device': 'Update Device',
        'Parameters': 'Parameters',
        'Temp Lower': 'Temp Lower',
        'Temp Target': 'Temp Target',
        'Temp Upper': 'Temp Upper',
        'Humid Lower': 'Humid Lower',
        'Humid Target': 'Humid Target',
        'Humid Upper': 'Humid Upper',
        'Press Lower': 'Press Lower',
        'Press Target': 'Press Target',
        'Press Upper': 'Press Upper',
        'Update': 'Update',
        
        // New translations added
        'Username': 'Username',
        'Password': 'Password',
        'LOGIN FMCS': 'LOGIN FMCS',
        'Logging out…': 'Logging out…',
        'Signing out…': 'Signing out…',
        'If you are not redirected automatically,': 'If you are not redirected automatically,',
        'click here': 'click here',
        
        // New translation added
        'All': 'All',
        
        // Login Messages
        'You need to log in to create an Action.': 'You need to log in to create an Action.',
        'Username must be 4–20 characters and contain only letters, numbers, and underscores.': 'Username must be 4–20 characters and contain only letters, numbers, and underscores.',
        'Password must be at least 8 characters long.': 'Password must be at least 8 characters long.',
        'Incorrect username or password.': 'Incorrect username or password.',
        "Action plan...": "Action plan...",
        "Example: John Doe": "Example: John Doe",
        "Loading families...": "Loading families...",
        "Delete this action?": "Delete this action?",
        "pending": "pending",
        "approved": "approved",
        "done": "done",
        "plans": "plans",
        "plan": "plan",

        //

        "Access denied: your account does not have permission to access System Settings.": "Access denied: your account does not have permission to access System Settings.",
        "Could not load data. Server error.": "Could not load data. Server error.",
        "No device selected. Please close and reopen the modal for a device.": "No device selected. Please close and reopen the modal for a device.",
        "Please add at least one full action plan (plan + date + owner).": "Please add at least one full action plan (plan + date + owner).",
        "Device ID not found in the device popup.": "Device ID not found in the device popup.",
        "Device ID is required for hourly reports.": "Device ID is required for hourly reports.",
        "Device configuration not found or is incomplete.": "Device configuration not found or is incomplete.",
        "Invalid data source table name.": "Invalid data source table name.",
        "Device ID and date range are required for device search.": "Device ID and date range are required for device search.",
        "Device configuration not found for device ID:": "Device configuration not found for device ID:",
        "Invalid date format. Expected 'Y-m-d H:i:s'.": "Invalid date format. Expected 'Y-m-d H:i:s'.",
        "Failed to load data. Please try again.": "Failed to load data. Please try again.",
        "Error loading": "Error loading",
        "No data found for the selected criteria.": "No data found for the selected criteria.",
        "Family not found in API list, will query with display name:": "Family not found in API list, will query with display name:",
        "Loading…": "Loading…",
        "Apply": "Apply",
        "Progress": "Progress",
        "ETA —": "ETA —",
        "— Select a family —": "— Select a family —",
        "Action already decided": "Action already decided",
        "No data": "No data",
        "Loading error:": "Loading error:",
        "Delete this action?": "Delete this action?",
        "Successfully deleted": "Successfully deleted",
        "Success": "Success",
        "Delete failed": "Delete failed",
        "Delete error": "Delete error",
        "error": "error",
        "Approve this action?": "Approve this action?",
        "Reject this action?": "Reject this action?",
        "Verification": "Verification",
        "Approved successfully.": "Approved successfully.",
        "rejected.": "Rejected.",
        "No action plans": "No action plans",
        "Load plans failed:": "Load plans failed:",
        "View actions": "View actions",
        "You must sign in to create an action. Viewing actions is available to everyone.": "You must sign in to create an action. Viewing actions is available to everyone.",
        "Create new action for": "Create new action for",
        "Device": "Device",
        "updated successfully": "updated successfully",
        "Error: No device ID provided for deletion.": "Error: No device ID provided for deletion.",
        "Error: Frequency Check Limit must be greater than Frequency.": "Error: Frequency Check Limit must be greater than Frequency.",
        "Error: Please fill in all required information for update.": "Error: Please fill in all required information for update.",
        "Error: Location ID": "Error: Location ID",
        "does not exist.": "does not exist.",
        "added successfully.": "added successfully.",
        "Error: Please fill in all required device information.": "Error: Please fill in all required device information.",
        "Device is Disconnected": "Device is Disconnected",
        "Processing...": "Processing...",
        "An error occurred. Please try again.": "An error occurred. Please try again.",
        "Action not found": "Action not found",
        "View all actions of": "View all actions of",
        "Auto from device — not editable": "Auto from device — not editable",
        "You must sign in to create an action.": "You must sign in to create an action.",
        "Cannot verify login status.": "Cannot verify login status.",
        "Describe the issue...": "Describe the issue...",
        "Please add at least one Action Plan.": "Please add at least one Action Plan.",
        "Open actions": "Open actions",
        "Are you sure you want to delete this action plan?": "Are you sure you want to delete this action plan?",
        
        
        "Example: TGN001_IN": "Example: TGN001_IN",
        "Example: 60": "Example: 60",
        "Example: 180": "Example: 180",
        'Select system type': 'Select system type',
        'Select location': 'Select location',
        'Factory Temperature': 'Factory Temperature',
        'All tabs': 'All tabs',
        'Example: Factory 1': 'Example: Factory 1',
        'Optional description': 'Optional description',
        'Please fill out this field': 'Please fill out this field',
        "TOOLS": "TOOLS",
        "todo": "todo",
        "-- Select issue type --": "-- Select issue type --",
        "Factual data": "Factual data",
        "Please select Issue Type.": "Please select Issue Type.",
        "Planned Date": "Planned Date",
        "All action plans of": "All action plans of",
        "TOOLS": "TOOLS",
        "todo": "todo",
        "-- Select issue type --": "-- Select issue type --",
        "Factual data": "Factual data",
        "Please select Issue Type.": "Please select Issue Type.",
        "Planned Date": "Planned Date",
        "All action plans of": "All action plans of",
        "Are you sure you have completed this action?": "Are you sure you have completed this action?",
        "Add Plan": "Add Plan",
        "Your account cannot create actions.": "Your account cannot create actions.",
        "Connected": "Connected",
        "Disconnected": "Disconnected",
        "Audit Trail": "Audit Trail",
        "OTP error!": "OTP error!",
        "Creates": "Creates",
        "Updates": "Updates",
        "Deletes": "Deletes",
        "Total Actions": "Total Actions",
        "Timestamp": "Timestamp",
        "Reason": "Reason",
        "Action Type": "Action Type",
        "All Actions": "All Actions",
        "Change date type": "Change date type",
        "Date Range": "Date Range",
        "Date": "Date",
        "Apply Filters": "Apply Filters",
        "Reset Filters": "Reset Filters",
        "Audit Diff": "Audit Diff",
        "Record #": "Record #",
        "Close": "Close",
        "Field": "Field",
        "Before": "Before",
        "After": "After",
        "Timeline View": "Timeline View",
        "Table View": "Table View",
        "Tuesday": "Tuesday",
        "Export CSV": "Export CSV",
        "Filters": "Filters",
        "Track all system changes and modifications": "Track all system changes and modifications",
        "View Diff": "View Diff",
        "Search by reason, user, ID...": "Search by reason, user, ID..."
    },
    
    'vi': {
        // Header
        'Dashboard': 'Bảng điều khiển',
        'Mold': 'Khuôn',
        'Tufting': 'Cắm sợi',
        'Tuft': 'Cắm sợi',
        'Blister': 'Đóng gói',
        'Online': 'Trực tuyến',
        'Warning': 'Cảnh báo',
        'Offline': 'Ngoại tuyến',
        'Total': 'Tổng cộng',
        'Flexible': 'Linh hoạt',
        'Action': 'Hành động',
        'System Settings': 'Cài đặt hệ thống',
        'Login': 'Đăng nhập',
        'Logout': 'Đăng xuất',
        'Monitoring': 'Giám sát',
        'Location Management': 'Quản lý vị trí',
        'Monitoring Dashboard': 'Bảng điều khiển giám sát',
        'Add New Device': 'Thêm thiết bị mới',
        
        // Device Status
        'Devices online': 'Thiết bị trực tuyến',
        'Devices breached thresholds': 'Thiết bị vượt ngưỡng',
        'Devices disconnected': 'Thiết bị mất kết nối',
        'Devices total': 'Tổng thiết bị',
        'Action total': 'Tổng hành động',
        
        // Modal Information
        'Information': 'Thông tin',
        'ID': 'Mã',
        'Process': 'Quy trình',
        'Mold Cavity': 'Lỗ khuôn',
        'Actual Cavity': 'Lỗ thực tế',
        'Capacity': 'Sản lượng',
        'Efficiency': 'Hiệu suất',
        'Eff.requirement': 'Yêu cầu hiệu suất',
        'Current Cycle': 'Chu kỳ hiện tại',
        'Target': 'Mục tiêu',
        'Upper Limit': 'Giới hạn trên',
        'Lower Limit': 'Giới hạn dưới',
        'Total lost pcs': 'Tổng sản phẩm lỗi',
        'Lost time': 'Thời gian mất',
        'Total Count': 'Tổng',
        
        // Chart and Controls
        'Hourly Efficiency': 'Hiệu suất theo giờ',
        '2 day ago': '2 ngày trước',
        'Yesterday': 'Hôm qua',
        'Today': 'Hôm nay',
        'From dateline': 'Từ ngày',
        'To dateline': 'Đến ngày',
        'SEARCH': 'TÌM KIẾM',
        'RESET': 'ĐẶT LẠI',
        'Total Output': 'Tổng sản lượng',
        'Average Cycle': 'Chu kỳ trung bình',
        'Export to Excel': 'Xuất Excel',
        
        // Loss Report
        'Loss & Idle Time Report': 'Báo cáo thời gian mất & nghỉ',
        
        // Action Modal
        '+ Add Action': '+ Thêm hành động',
        'Create new Action': 'Tạo hành động mới',
        'Issue ID': 'Mã sự cố',
        'Issue Description': 'Mô tả sự cố',
        'Issue Type': 'Loại sự cố',
        'Actual Efficiency': 'Hiệu suất thực tế',
        'Actual Cycle': 'Chu kỳ thực tế',
        'Efficiency Required': 'Hiệu suất yêu cầu',
        'Priority': 'Ưu tiên',
        'Due': 'Hạn',
        'Creator': 'Người tạo',
        'Action Plans': 'Kế hoạch hành động',
        '+ Add Plan': '+ Thêm kế hoạch',
        'Action ID': 'Mã hành động',
        'Planned completion date': 'Ngày hoàn thành dự kiến',
        'Owner': 'Người phụ trách',
        'Cancel': 'Hủy',
        'Create Action': 'Tạo hành động',
        
        // Priority levels
        'low': 'Thấp',
        'medium': 'Trung bình',
        'high': 'Cao',
        'urgent': 'Khẩn cấp',
        
        // Common
        'Close': 'Đóng',
        'Save': 'Lưu',
        'Delete': 'Xóa',
        'Edit': 'Sửa',
        'View': 'Xem',
        'NO DATA': 'KHÔNG',
        'BACK': 'QUAY LẠI',
        'VIEW MORE': 'XEM THÊM',
        'pcs_per_minute': 'Cái/phút',
        'rpm': 'Vòng/phút',
        'brushes_per_cycle': 'Bàn chải/chu kỳ',
        'pcs': 'Cây',
        'Shift Insert': 'Khuôn robot',
        'Manual Insert': 'Khuôn tay',
        'Rotating': 'Khuôn xoay',
        'Shot': 'Khuôn',
        'hole/brush': 'Lỗ/Bàn chải',
        'family': 'Loại hàng',
        'chiller': 'Máy làm lạnh',
        'vacuum tank': 'Buồng chân không',
        'compressor': 'Máy nén khí',
        'end air pressure': 'Áp suất đầu cuối',
        'air tank': 'Buồng hơi',
        'workshop temperature': 'Nhiệt độ xưởng',
        
        // Tabs
        'Issue': 'Sự cố',
        'Cooling Tower': 'Tháp làm mát',
        'Chiller': 'Máy làm lạnh',
        'Vacuum Tank': 'Bể chân không',
        'Air Dryer': 'Máy sấy khí',
        'Compressor': 'Máy nén',
        'End Air Pressure': 'Áp suất khí cuối',
        'Air Tank': 'Bể khí',
        'Air Conditioner': 'Máy lạnh',
        'Workshop Temperature': 'Nhiệt độ xưởng',
        
        // Table Headers
        'System': 'Hệ thống',
        'Device ID': 'ID Thiết bị',
        'Location': 'Vị trí',
        'Time': 'Thời gian',
        'Total Count (h)': 'Tổng (h)',
        'Status': 'Trạng thái',
        'Connection': 'Kết nối',
        'Temperature': 'Nhiệt độ',
        'Humidity': 'Độ ẩm',
        'Pressure': 'Áp suất',
        'Actual': 'Thực tế',
        'Lower': 'Thấp hơn',
        'Target': 'Mục tiêu',
        'Upper': 'Cao hơn',
        'Add': 'Thêm',
        'Mold ID (Unique)': 'Mã khuôn (duy nhất)',
        'Family': 'Loại hàng',
        'Model': 'Loại máy',
        'Manufacturer': 'Nhà sản xuất',
        'Mfg. Date': 'Ngày sản xuất',
        'Capacity (1h)': 'Sản lượng (1h)',
        'Unit': 'Đơn vị',
        'Mold Type': 'Loại khuôn',
        'Efficiency Limit(%)': 'Giới hạn hiệu suất(%)',
        'Limits (s) (LL/TG/UL)': 'Giới hạn (s) (LL/TG/UL)',
        'Fre. Check Connected (s)': 'Tần suất kiểm tra kết nối (s)',
        'Fre. Check Limit (s)': 'Tần suất kiểm tra giới hạn (s)',
        'Actions': 'Hành động',
        'Injection ID': 'Mã máy cán',
        'Frequency Check Connected (s)': 'Tần suất kiểm tra kết nối (s)',
        'Frequency Check Limit (s)': 'Tần suất kiểm tra giới hạn (s)',
        'Tufting ID': 'Mã cắm sợi',
        'Flex': 'Linh hoạt',
        'Hole/Brush': 'Lỗ/Bàn chải',
        'Limits (pcs) (LL/TG/UL)': 'Giới hạn (cái) (LL/TG/UL)',
        'End-rounding ID': 'Mã máy mài',
        'Blister ID': 'Mã đóng gói',
        'Brushes/Cycle': 'Bàn chải/Chu kỳ',
        'Limits (cycles) (LL/TG/UL)': 'Giới hạn (chu kỳ) (LL/TG/UL)',
        'Injection': 'Máy cán',
        'End-rounding': 'Máy mài',
        'Manufacturing Date': 'Ngày sản xuất',
        'Manual': 'Khuôn tay',
        'Auto': 'Khuôn tự động',
        'Number of Cavities': 'Số khoang',
        'Machine Type': 'Loại máy',
        'Lower Limit (s)': 'Giới hạn dưới (s)',
        'Target (s)': 'Mục tiêu (s)',
        'Upper Limit (s)': 'Giới hạn trên (s)',
        
        // Pagination
        'Previous': 'Trước',
        'Next': 'Tiếp',
        'Page': 'Trang',
        'of': 'của',
        
        // Modal
        'All tabs': 'Tất cả tab',
        'Search tab...': 'Tìm kiếm tab...',
        
        // Tooltips
        'Device is Disconnected': 'Thiết bị bị ngắt kết nối',
        'No issues detected': 'Không phát hiện sự cố',
        'No data available': 'Không có dữ liệu',
        
        // New translations for JavaScript strings
        'DateTime': 'Ngày giờ',
        'Cavities': 'Khoang',
        'CycleTime': 'Thời gian chu kỳ (s)',
        'Output': 'Sản lượng (chiếc)',
        'NoDataToExport': 'Không có dữ liệu để xuất. Vui lòng thực hiện tìm kiếm trước.',
        'ErrorUnknownDeviceType': 'Loại thiết bị không xác định để xuất.',
        'FailedToExport': 'Không thể xuất dữ liệu.',
        'SelectBothDates': 'Vui lòng chọn cả ngày "Từ" và "Đến".',
        'BrushesPerCycle': 'Bàn chải/Chu kỳ',
        'CycleCount': 'Số chu kỳ',
        'OutputPcsMin': 'Sản lượng (chiếc/phút)',
        'NoDataStructure': 'Không có cấu trúc dữ liệu',
        'AverageOutput': 'Sản lượng trung bình',
        'Cycle': 'Chu kỳ',
        'lost_pcs': 'Cái lỗi (cái)',
        'idle_breakdown': 'Ngừng/Trục trặc (giây)',
        'AverageCycle': 'Chu kỳ trung bình',
        'Select': 'Chọn',
        'Type': 'Loại',
        'In progress': 'Đang tiến hành',
        'Complete': 'Hoàn thành',
        'Description Of Issue': 'Mô tả sự cố',
        'Created Date': 'Ngày tạo',
        'Planned Completion Date': 'Ngày dự kiến hoàn thành',
        'Machine': 'Máy',
        'Open': 'Đang Mở',
        'Issue': 'Vấn đề',
        'Action plan': 'Kế hoạch hành động',
        'Created by': 'Tạo bởi',
        'Created': 'Đã tạo',
        'Action Status': 'Trạng thái hành động',
        'Approval': 'Phê duyệt',
        'Manage': 'Quản lý',
        
        // Additional translations from previous JSON
        'EMS Dashboard': 'Bảng điều khiển EMS',
        'Overall': 'Tổng quan',
        'Running': 'Đang chạy',
        'Break Down': 'Hỏng hóc',
        '07:00 ~ 19:00': '07:00 ~ 19:00',
        '19:00 ~ 07:00': '19:00 ~ 07:00',
        'Average': 'Trung bình',
        'Molding': 'Khuôn',
        'Blistering': 'Đóng gói',
        'Machine ID': 'ID Máy',
        'Capacity / hr': 'Công suất / giờ',
        'EMS Monitoring': 'Giám sát EMS',
        'Machine Details': 'Chi tiết máy',
        'Efficiency Report': 'Báo cáo hiệu suất',
        'Output Report': 'Báo cáo sản lượng',
        'Output Summary': 'Tóm tắt sản lượng',
        'Output (pcs)': 'Sản lượng (cái)',
        'Lost (pcs)': 'Mất (cái)',
        'All Families': 'Tất cả loại hàng',
        'SubTotal': 'Tổng phụ',
        'No data': 'Không có dữ liệu',
        'Loading...': 'Đang tải...',
        'No data found for this filter.': 'Không tìm thấy dữ liệu cho bộ lọc này.',
        'Failed to load data. Please try again.': 'Không tải được dữ liệu. Vui lòng thử lại.',
        'Failed to load families:': 'Không tải được danh sách gia đình:',
        'Error loading': 'Lỗi khi tải',
        
        // Previous new translations
        'Mold ID': 'ID Khuôn',
        'Details': 'Chi tiết',
        'Report': 'Báo cáo',
        'From (VN)': 'Từ',
        'To (VN)': 'Đến',
        
        // New translation added
        'Approve Actions': 'Phê duyệt hành động',
        
        // Previous new translations added
        'Update Location': 'Cập nhật vị trí',
        'Location Name': 'Tên vị trí',
        'Description': 'Mô tả',
        'Add New Location': 'Thêm vị trí mới',
        
        // Previous new translations added
        'Welcome': 'Chào mừng',
        'Data': 'Dữ liệu',
        'Frequency': 'Tần suất',
        'Check limit': 'Kiểm tra giới hạn',
        'Temp (°C)': 'Nhiệt độ (°C)',
        'Humidity (%)': 'Độ ẩm (%)',
        'Pressure (kg/cm²)': 'Áp suất (kg/cm²)',
        
        // Previous new translations added
        'Frequency (seconds)': 'Tần suất (giây)',
        'System Type': 'Loại hệ thống',
        'Update Device': 'Cập nhật thiết bị',
        'Parameters': 'Thông số',
        'Temp Lower': 'Nhiệt độ thấp',
        'Temp Target': 'Nhiệt độ mục tiêu',
        'Temp Upper': 'Nhiệt độ cao',
        'Humid Lower': 'Độ ẩm thấp',
        'Humid Target': 'Độ ẩm mục tiêu',
        'Humid Upper': 'Độ ẩm cao',
        'Press Lower': 'Áp suất thấp',
        'Press Target': 'Áp suất mục tiêu',
        'Press Upper': 'Áp suất cao',
        'Update': 'Cập nhật',
        
        // New translations added
        'Username': 'Tên người dùng',
        'Password': 'Mật khẩu',
        'LOGIN FMCS': 'ĐĂNG NHẬP FMCS',
        'Logging out…': 'Đang đăng xuất…',
        'Signing out…': 'Đang thoát…',
        'If you are not redirected automatically,': 'Nếu bạn không được chuyển hướng tự động,',
        'click here': 'nhấn vào đây',
        
        // New translation added
        'All': 'Tất cả',
        
        // Login Messages
        'You need to log in to create an Action.': 'Bạn cần đăng nhập để tạo hành động.',
        'Username must be 4–20 characters and contain only letters, numbers, and underscores.': 'Tên người dùng phải có 4–20 ký tự và chỉ chứa chữ cái, số và dấu gạch dưới.',
        'Password must be at least 8 characters long.': 'Mật khẩu phải có ít nhất 8 ký tự.',
        'Incorrect username or password.': 'Tên người dùng hoặc mật khẩu không đúng.',
        "Action plan...": "Kế hoạch hành động...",
        "Example: John Doe": "Ví dụ: John Doe",
        "Loading families...": "Đang tải các loại hàng...",
        "Delete this action?": "Xóa hành động này?",
        "pending": "đang chờ xử lý",
        "approved": "đã phê duyệt",
        "done": "hoàn tất",
        "plans": "các kế hoạch",
        "plan": "kế hoạch",
        "Access denied: your account does not have permission to access System Settings.": "Từ chối truy cập: tài khoản của bạn không có quyền truy cập Cài đặt hệ thống.",
        "Could not load data. Server error.": "Không thể tải dữ liệu. Lỗi máy chủ.",
        "No device selected. Please close and reopen the modal for a device.": "Chưa chọn thiết bị. Vui lòng đóng và mở lại cửa sổ thiết bị.",
        "Please add at least one full action plan (plan + date + owner).": "Vui lòng thêm ít nhất một kế hoạch hành động đầy đủ (kế hoạch + ngày + người phụ trách).",
        "Device ID not found in the device popup.": "Không tìm thấy Device ID trong cửa sổ thiết bị.",
        "Device ID is required for hourly reports.": "Cần Device ID cho báo cáo theo giờ.",
        "Device configuration not found or is incomplete.": "Không tìm thấy hoặc cấu hình thiết bị chưa đầy đủ.",
        "Invalid data source table name.": "Tên bảng nguồn dữ liệu không hợp lệ.",
        "Device ID and date range are required for device search.": "Cần Device ID và khoảng ngày để tìm kiếm thiết bị.",
        "Device configuration not found for device ID:": "Không tìm thấy cấu hình thiết bị cho Device ID:",
        "Invalid date format. Expected 'Y-m-d H:i:s'.": "Định dạng ngày không hợp lệ. Định dạng đúng: 'Y-m-d H:i:s'.",
        "Failed to load data. Please try again.": "Tải dữ liệu thất bại. Vui lòng thử lại.",
        "Error loading": "Lỗi tải dữ liệu",
        "No data found for the selected criteria.": "Không tìm thấy dữ liệu theo tiêu chí đã chọn.",
        "Family not found in API list, will query with display name:": "Không tìm thấy Family trong danh sách API, sẽ truy vấn bằng tên hiển thị:",
        "Loading…": "Đang tải…",
        "Apply": "Áp dụng",
        "Progress": "Tiến trình",
        "ETA —": "Thời gian dự kiến —",
        "— Select a family —": "— Chọn một family —",
        "Action already decided": "Hành động đã được quyết định",
        "No data": "Không có dữ liệu",
        "Loading error:": "Lỗi tải:",
        "Delete this action?": "Xóa hành động này?",
        "Successfully deleted": "Xóa thành công",
        "Success": "Thành công",
        "Delete failed": "Xóa thất bại",
        "Delete error": "Lỗi khi xóa",
        "error": "lỗi",
        "Approve this action?": "Phê duyệt hành động này?",
        "Reject this action?": "Từ chối hành động này?",
        "Verification": "Xác nhận",
        "Approved successfully.": "Phê duyệt thành công.",
        "rejected.": "Đã từ chối.",
        "No action plans": "Không có kế hoạch hành động",
        "Load plans failed:": "Tải kế hoạch thất bại:",
        "View actions": "Xem hành động",
        "You must sign in to create an action. Viewing actions is available to everyone.": "Bạn phải đăng nhập để tạo hành động. Xem hành động thì ai cũng có thể xem.",
        "Create new action for": "Tạo hành động mới cho",
        "Device": "Thiết bị",
        "updated successfully": "cập nhật thành công",
        "Error: No device ID provided for deletion.": "Lỗi: Không có Device ID để xóa.",
        "Error: Frequency Check Limit must be greater than Frequency.": "Lỗi: Giới hạn kiểm tra tần suất phải lớn hơn tần suất.",
        "Error: Please fill in all required information for update.": "Lỗi: Vui lòng điền đầy đủ thông tin bắt buộc để cập nhật.",
        "Error: Location ID": "Lỗi: Location ID",
        "does not exist.": "không tồn tại.",
        "added successfully.": "thêm thành công.",
        "Error: Please fill in all required device information.": "Lỗi: Vui lòng điền đầy đủ thông tin thiết bị.",
        "Device is Disconnected": "Thiết bị đã ngắt kết nối",
        "Processing...": "Đang xử lý...",
        "An error occurred. Please try again.": "Có lỗi xảy ra. Vui lòng thử lại.",
        "Action not found": "Không tìm thấy hành động",
        "View all actions of": "Xem tất cả hành động của",
        "Auto from device — not editable": "Tự động từ thiết bị — không thể chỉnh sửa",
        "You must sign in to create an action.": "Bạn phải đăng nhập để tạo hành động.",
        "Cannot verify login status.": "Không thể xác minh trạng thái đăng nhập.",
        "Describe the issue...": "Mô tả sự cố...",
        "Please add at least one Action Plan.": "Vui lòng thêm ít nhất một Kế hoạch hành động.",
        "Open actions": "Các hành động đang mở",
        "Are you sure you want to delete this action plan?": "Bạn có chắc chắn muốn xóa kế hoạch hành động này không?",
        "Exemple: John Doe": "Ví dụ: John Doe",
        "Example: TGN001_IN": "Ví dụ: TGN001_IN",
        "Example: 60": "Ví dụ: 60",
        "Example: 180": "Ví dụ: 180",
        'Select system type': 'Chọn loại hệ thống',
        'Select location': 'Chọn vị trí',
        'Factory Temperature': 'Nhiệt độ nhà máy',
        'All tabs': 'Tất cả tab',
        'Example: Factory 1': 'Ví dụ: Nhà máy 1',
        'Optional description': 'Mô tả tùy chọn',
        'Please fill out this field': 'Vui lòng điền vào trường này',
        "TOOLS": "CÔNG CỤ",
        "todo": "việc cần làm",
        "-- Select issue type --": "-- Chọn loại sự cố --",
        "Factual data": "Dữ liệu thực tế",
        "Please select Issue Type.": "Vui lòng chọn loại sự cố.",
        "Planned Date": "Ngày dự kiến",
        "All action plans of": "Tất cả kế hoạch hành động của",
        "TOOLS": "CÔNG CỤ",
        "todo": "việc cần làm",
        "-- Select issue type --": "-- Chọn loại sự cố --",
        "Factual data": "Dữ liệu thực tế",
        "Please select Issue Type.": "Vui lòng chọn loại sự cố.",
        "Planned Date": "Ngày dự kiến",
        "All action plans of": "Tất cả kế hoạch hành động của",
        "Are you sure you have completed this action?": "Bạn có chắc rằng đã hoàn thành hành động này không?",
        "Add Plan": "Thêm kế hoạch",
        "Your account cannot create actions.": "Tài khoản của bạn không thể tạo hành động.",
        "Connected": "Đã kết nối",
        "Disconnected": "Mất kết nối",
        "Audit Trail": "Bản ghi kiểm toán",
        "OTP error!": "Lỗi OTP!",
        "Creates": "Tạo",
        "Updates": "Cập nhật",
        "Deletes": "Xóa",
        "Total Actions": "Tổng số hành động",
        "Timestamp": "Dấu thời gian",
        "Reason": "Lý do",
        "Action Type": "Loại hành động",
        "All Actions": "Tất cả hành động",
        "Change date type": "Thay đổi loại ngày",
        "Date Range": "Khoảng ngày",
        "Date": "Ngày",
        "Apply Filters": "Áp dụng bộ lọc",
        "Reset Filters": "Đặt lại bộ lọc",
        "Audit Diff": "Chênh lệch kiểm toán",
        "Record #": "Bản ghi #",
        "Close": "Đóng",
        "Field": "Trường",
        "Before": "Trước",
        "After": "Sau",
        "Timeline View": "Xem dòng thời gian",
        "Table View": "Xem dạng bảng",
        "Create": "Tạo",
        "Tuesday": "Thứ Ba",
        "Export CSV": "Xuất CSV",
        "Filters": "Bộ lọc",
        "Track all system changes and modifications": "Theo dõi mọi thay đổi và chỉnh sửa của hệ thống",
        "View Diff": "Xem khác biệt",
        "Search by reason, user, ID...": "Tìm kiếm theo lý do, người dùng, ID..."
    },
    
    'zh-TW': {
        // Header
        'Dashboard': '儀錶板',
        'Mold': '模具',
        'Tufting': '植毛',
        'Tuft': '植毛',
        'Blister': '泡罩',
        'Online': '線上',
        'Warning': '警告',
        'Offline': '離線',
        'Total': '總計',
        'Flexible': '彈性',
        'Action': '行動',
        'System Settings': '系統設定',
        'Login': '登入',
        'Logout': '登出',
        'Monitoring': '監控',
        'Location Management': '位置管理',
        'Monitoring Dashboard': '監控儀錶板',
        'Add New Device': '新增設備',
        
        // Device Status
        'Devices online': '設備線上',
        'Devices breached thresholds': '設備超出閾值',
        'Devices disconnected': '設備斷線',
        'Devices total': '總設備數',
        'Action total': '總行動數',
        
        // Modal Information
        'Information': '資訊',
        'ID': '編號',
        'Process': '流程',
        'Mold Cavity': '模穴',
        'Actual Cavity': '實際穴數',
        'Capacity': '產量',
        'Efficiency': '效率',
        'Eff.requirement': '效率需求',
        'Current Cycle': '當前週期',
        'Target': '目標',
        'Upper Limit': '上限',
        'Lower Limit': '下限',
        'Total lost pcs': '總損失件數',
        'Lost time': '損失時間',
        'Total Count': '總計數',
        
        // Chart and Controls
        'Hourly Efficiency': '小時效率',
        '2 day ago': '2天前',
        'Yesterday': '昨天',
        'Today': '今天',
        'From dateline': '起始日期',
        'To dateline': '結束日期',
        'SEARCH': '搜尋',
        'RESET': '重置',
        'Total Output': '總產出',
        'Average Cycle': '平均週期',
        'Export to Excel': '匯出Excel',
        
        // Loss Report
        'Loss & Idle Time Report': '損失及閒置時間報告',
        
        // Action Modal
        '+ Add Action': '+ 新增行動',
        'Create new Action': '創建新行動',
        'Issue ID': '問題編號',
        'Issue Description': '問題描述',
        'Issue Type': '問題類型',
        'Actual Efficiency': '實際效率',
        'Actual Cycle': '實際週期',
        'Efficiency Required': '所需效率',
        'Priority': '優先級',
        'Due': '到期',
        'Creator': '創建者',
        'Action Plans': '行動計劃',
        '+ Add Plan': '+ 新增計劃',
        'Action ID': '行動編號',
        'Planned completion date': '計劃完成日期',
        'Owner': '負責人',
        'Cancel': '取消',
        'Create Action': '創建行動',
        
        // Priority levels
        'low': '低',
        'medium': '中',
        'high': '高',
        'urgent': '緊急',
        
        // Common
        'Close': '關閉',
        'Save': '保存',
        'Delete': '刪除',
        'Edit': '編輯',
        'View': '查看',
        'NO DATA': '無資料',
        'BACK': '返回',
        'VIEW MORE': '查看更多',
        'pcs_per_minute': '件/分钟',
        'rpm': '转/分钟',
        'brushes_per_cycle': '刷子/循环',
        'pcs': '件',
        'Shift Insert': '模具機器人',
        'Manual Insert': '手動模具',
        'Rotating': '旋轉模具',
        'Shot': '模具',
        'hole/brush': '孔/刷子',
        'family': '類型',
        'chiller': '冷水機',
        'vacuum tank': '真空槽',
        'compressor': '壓縮機',
        'end air pressure': '末端空氣壓力',
        'air tank': '空氣槽',
        'workshop temperature': '車間溫度',
        
        // Tabs
        'Issue': '問題',
        'Cooling Tower': '冷卻塔',
        'Chiller': '冷水機',
        'Vacuum Tank': '真空槽',
        'Air Dryer': '空氣乾燥器',
        'Compressor': '壓縮機',
        'End Air Pressure': '末端空氣壓力',
        'Air Tank': '空氣槽',
        'Air Conditioner': '空調',
        'Workshop Temperature': '車間溫度',
        
        // Table Headers
        'System': '系統',
        'Device ID': '設備ID',
        'Location': '位置',
        'Time': '時間',
        'Total Count (h)': '總計數 (小時)',
        'Status': '狀態',
        'Connection': '連接',
        'Temperature': '溫度',
        'Humidity': '濕度',
        'Pressure': '壓力',
        'Actual': '實際',
        'Lower': '下限',
        'Target': '目標',
        'Upper': '上限',
        'Add': '新增',
        'Mold ID (Unique)': '模具編號（唯一）',
        'Family': '類型',
        'Model': '型號',
        'Manufacturer': '製造商',
        'Mfg. Date': '製造日期',
        'Capacity (1h)': '產能 (1小時)',
        'Unit': '單位',
        'Mold Type': '模具類型',
        'Efficiency Limit(%)': '效率限制(%)',
        'Limits (s) (LL/TG/UL)': '限制 (秒) (下限/目標/上限)',
        'Fre. Check Connected (s)': '頻率檢查連接 (秒)',
        'Fre. Check Limit (s)': '頻率檢查限制 (秒)',
        'Actions': '行動',
        'Injection ID': '壓延機編號',
        'Frequency Check Connected (s)': '頻率檢查連接 (秒)',
        'Frequency Check Limit (s)': '頻率檢查限制 (秒)',
        'Tufting ID': '植毛編號',
        'Flex': '彈性',
        'Hole/Brush': '孔/刷子',
        'Limits (pcs) (LL/TG/UL)': '限制 (件) (下限/目標/上限)',
        'End-rounding ID': '磨圓機編號',
        'Blister ID': '泡罩編號',
        'Brushes/Cycle': '刷子/週期',
        'Limits (cycles) (LL/TG/UL)': '限制 (週期) (下限/目標/上限)',
        'Injection': '壓延機',
        'End-rounding': '磨圓機',
        'Manufacturing Date': '製造日期',
        'Manual': '手動模具',
        'Auto': '自動模具',
        'Number of Cavities': '模穴數量',
        'Machine Type': '機器類型',
        'Lower Limit (s)': '下限 (秒)',
        'Target (s)': '目標 (秒)',
        'Upper Limit (s)': '上限 (秒)',
        
        // Pagination
        'Previous': '上一頁',
        'Next': '下一頁',
        'Page': '頁面',
        'of': '/',
        
        // Modal
        'All tabs': '所有標籤',
        'Search tab...': '搜尋標籤...',
        
        // Tooltips
        'Device is Disconnected': '設備已斷線',
        'No issues detected': '未偵測到問題',
        'No data available': '無可用資料',
        
        // New translations for JavaScript strings
        'DateTime': '日期時間',
        'Cavities': '模穴',
        'CycleTime': '週期時間 (秒)',
        'Output': '產量 (件)',
        'NoDataToExport': '無數據可匯出。請先執行搜尋。',
        'ErrorUnknownDeviceType': '未知設備類型，無法匯出。',
        'FailedToExport': '無法匯出數據。',
        'SelectBothDates': '請選擇"起始"和"結束"日期。',
        'BrushesPerCycle': '刷子/週期',
        'CycleCount': '週期計數',
        'OutputPcsMin': '產量 (件/分鐘)',
        'NoDataStructure': '無數據結構可用',
        'AverageOutput': '平均產量',
        'Cycle': '循環',
        'lost_pcs': '損失件(件)',
        'idle_breakdown': '停機/故障(秒)',
        'AverageCycle': '平均循環',
        'Select': '選擇',
        'Type': '類型',
        'In progress': '進行中',
        'Complete': '完成',
        'Description Of Issue': '問題描述',
        'Created Date': '建立日期',
        'Planned Completion Date': '預計完成日期',
        'Machine': '機器',
        'Open': '打開',
        'Issue': '問題',
        'Action plan': '行動計劃',
        'Created by': '建立者',
        'Created': '已建立',
        'Action Status': '行動狀態',
        'Approval': '批准',
        'Manage': '管理',
        
        // Additional translations from previous JSON
        'EMS Dashboard': 'EMS儀錶板',
        'Overall': '總覽',
        'Running': '運行中',
        'Break Down': '故障',
        '07:00 ~ 19:00': '07:00 ~ 19:00',
        '19:00 ~ 07:00': '19:00 ~ 07:00',
        'Average': '平均',
        'Molding': '成型',
        'Blistering': '吸塑',
        'Machine ID': '機器ID',
        'Capacity / hr': '每小時產能',
        'EMS Monitoring': 'EMS監控',
        'Machine Details': '機器詳情',
        'Efficiency Report': '效率報告',
        'Output Report': '產量報告',
        'Output Summary': '產量總結',
        'Output (pcs)': '產量（件）',
        'Lost (pcs)': '丟失（件）',
        'All Families': '所有家族',
        'SubTotal': '小計',
        'No data': '無數據',
        'Loading...': '載入中...',
        'No data found for this filter.': '未找到此篩選條件的數據。',
        'Failed to load data. Please try again.': '載入數據失敗，請重試。',
        'Failed to load families:': '無法載入家族列表：',
        'Error loading': '載入錯誤',
        
        // Previous new translations
        'Mold ID': '模具ID',
        'Details': '詳情',
        'Report': '報告',
        'From (VN)': '從',
        'To (VN)': '至',
        
        // New translation added
        'Approve Actions': '批准行動',
        
        // Previous new translations added
        'Update Location': '更新位置',
        'Location Name': '位置名稱',
        'Description': '描述',
        'Add New Location': '新增位置',
        
        // Previous new translations added
        'Welcome': '歡迎',
        'Data': '數據',
        'Frequency': '頻率',
        'Check limit': '檢查限制',
        'Temp (°C)': '溫度 (°C)',
        'Humidity (%)': '濕度 (%)',
        'Pressure (kg/cm²)': '壓力 (kg/cm²)',
        
        // Previous new translations added
        'Frequency (seconds)': '頻率 (秒)',
        'System Type': '系統類型',
        'Update Device': '更新設備',
        'Parameters': '參數',
        'Temp Lower': '溫度下限',
        'Temp Target': '溫度目標',
        'Temp Upper': '溫度上限',
        'Humid Lower': '濕度下限',
        'Humid Target': '濕度目標',
        'Humid Upper': '濕度上限',
        'Press Lower': '壓力下限',
        'Press Target': '壓力目標',
        'Press Upper': '壓力上限',
        'Update': '更新',
        
        // New translations added
        'Username': '用戶名',
        'Password': '密碼',
        'LOGIN FMCS': '登入FMCS',
        'Logging out…': '正在登出…',
        'Signing out…': '正在退出…',
        'If you are not redirected automatically,': '如果您未被自動重定向，',
        'click here': '點擊此處',
        
        // New translation added
        'All': '全部',
        
        // Login Messages
        'You need to log in to create an Action.': '您需要登錄以創建行動。',
        'Username must be 4–20 characters and contain only letters, numbers, and underscores.': '用戶名必須為4-20個字符，且僅包含字母、數字和下劃線。',
        'Password must be at least 8 characters long.': '密碼必須至少8個字符長。',
        'Incorrect username or password.': '用戶名或密碼不正確。',
        "Action plan...": "行動計劃...",
        "Example: John Doe": "示例：約翰·多伊",
        "Loading families...": "正在載入產品類型...",
        "Delete this action?": "刪除此操作？",
        "pending": "待處理",
        "approved": "已核准",
        "done": "完成",
        "plans": "計畫",
        "plan": "計劃",
        "Access denied: your account does not have permission to access System Settings.": "拒絕存取：您的帳號沒有權限進入系統設定。",
        "Could not load data. Server error.": "無法載入資料。伺服器錯誤。",
        "No device selected. Please close and reopen the modal for a device.": "未選擇裝置。請關閉並重新開啟裝置視窗。",
        "Please add at least one full action plan (plan + date + owner).": "請至少新增一個完整的行動計劃（計劃 + 日期 + 負責人）。",
        "Device ID not found in the device popup.": "在裝置視窗中找不到 Device ID。",
        "Device ID is required for hourly reports.": "每小時報告需要 Device ID。",
        "Device configuration not found or is incomplete.": "找不到裝置設定或設定不完整。",
        "Invalid data source table name.": "資料來源表名稱無效。",
        "Device ID and date range are required for device search.": "搜尋裝置需要 Device ID 和日期範圍。",
        "Device configuration not found for device ID:": "找不到此 Device ID 的裝置設定：",
        "Invalid date format. Expected 'Y-m-d H:i:s'.": "日期格式無效。應為 'Y-m-d H:i:s'。",
        "Failed to load data. Please try again.": "資料載入失敗。請再試一次。",
        "Error loading": "載入錯誤",
        "No data found for the selected criteria.": "依選擇的條件找不到資料。",
        "Family not found in API list, will query with display name:": "在 API 清單中找不到 Family，將以顯示名稱查詢：",
        "Loading…": "載入中…",
        "Apply": "套用",
        "Progress": "進度",
        "ETA —": "預估時間 —",
        "— Select a family —": "— 選擇一個 Family —",
        "Action already decided": "行動已決定",
        "No data": "無資料",
        "Loading error:": "載入錯誤：",
        "Delete this action?": "要刪除此行動嗎？",
        "Successfully deleted": "刪除成功",
        "Success": "成功",
        "Delete failed": "刪除失敗",
        "Delete error": "刪除錯誤",
        "error": "錯誤",
        "Approve this action?": "要批准此行動嗎？",
        "Reject this action?": "要拒絕此行動嗎？",
        "Verification": "驗證",
        "Approved successfully.": "批准成功。",
        "rejected.": "已拒絕。",
        "No action plans": "沒有行動計劃",
        "Load plans failed:": "載入計劃失敗：",
        "View actions": "檢視行動",
        "You must sign in to create an action. Viewing actions is available to everyone.": "您必須登入才能建立行動。查看行動對所有人開放。",
        "Create new action for": "建立新行動於",
        "Device": "裝置",
        "updated successfully": "更新成功",
        "Error: No device ID provided for deletion.": "錯誤：未提供要刪除的裝置 ID。",
        "Error: Frequency Check Limit must be greater than Frequency.": "錯誤：檢查頻率上限必須大於頻率。",
        "Error: Please fill in all required information for update.": "錯誤：請填寫所有必要的更新資訊。",
        "Error: Location ID": "錯誤：位置 ID",
        "does not exist.": "不存在。",
        "added successfully.": "新增成功。",
        "Error: Please fill in all required device information.": "錯誤：請填寫所有必要的裝置資訊。",
        "Device is Disconnected": "裝置已中斷連線",
        "Processing...": "處理中...",
        "An error occurred. Please try again.": "發生錯誤。請再試一次。",
        "Action not found": "找不到行動",
        "View all actions of": "檢視所有行動於",
        "Auto from device — not editable": "來自裝置自動產生 — 無法編輯",
        "You must sign in to create an action.": "您必須登入才能建立行動。",
        "Cannot verify login status.": "無法驗證登入狀態。",
        "Describe the issue...": "描述問題...",
        "Please add at least one Action Plan.": "請至少新增一個行動計劃。",
        "Open actions": "開啟的行動",
        "Are you sure you want to delete this action plan?": "您確定要刪除此行動計劃嗎？",

        "Example: TGN001_IN": "示例: TGN001_IN",
        "Example: 60": "示例: 60",
        "Example: 180": "示例: 180",
        'Select system type': '選擇系統類型',
        'Select location': '選擇地點',
        'Factory Temperature': '工廠溫度',
        'All tabs': '所有標籤',
        'Example: Factory 1': '示例：工廠1',
        'Optional description': '可選描述',
        'Please fill out this field': '請填寫此欄位',
        "TOOLS": "工具",
        "todo": "待辦事項",
        "-- Select issue type --": "-- 選擇問題類型 --",
        "Factual data": "實際資料",
        "Please select Issue Type.": "請選擇問題類型。",
        "Planned Date": "預定日期",
        "All action plans of": "所有行動計劃於",
        "TOOLS": "工具",
        "todo": "待辦事項",
        "-- Select issue type --": "-- 選擇問題類型 --",
        "Factual data": "實際資料",
        "Please select Issue Type.": "請選擇問題類型。",
        "Planned Date": "預定日期",
        "All action plans of": "所有行動計劃於",
        "Are you sure you have completed this action?": "您確定已完成此行動嗎？",
        "Add Plan": "新增計劃",    
        "Your account cannot create actions.": "您的帳戶無法建立操作。",
        "Connected": "已連接",
        "Disconnected": "已斷開",
        "Audit Trail": "稽核追蹤",
        "OTP error!": "OTP 錯誤！",
        "Creates": "建立",
        "Updates": "更新",
        "Deletes": "刪除",
        "Total Actions": "操作總數",
        "Timestamp": "時間戳記",
        "Reason": "原因",
        "Action Type": "操作類型",
        "All Actions": "全部操作",
        "Change date type": "變更日期類型",
        "Date Range": "日期範圍",
        "Date": "日期",
        "Apply Filters": "套用篩選",
        "Reset Filters": "重設篩選",
        "Audit Diff": "稽核差異",
        "Record #": "記錄 #",
        "Close": "關閉",
        "Field": "欄位",
        "Before": "之前",
        "After": "之後",
        "Timeline View": "時間線檢視",
        "Table View": "表格檢視",
        "Create": "建立",
        "Export CSV": "匯出 CSV",
        "Filters": "篩選",
        "Track all system changes and modifications": "追蹤所有系統變更與修改",
        "View Diff": "檢視差異",
        "Search by reason, user, ID...": "依原因、使用者、ID 搜尋..."
    },
    
    'zh-CN': {
        // Header
        'Dashboard': '仪表板',
        'Mold': '模具',
        'Tufting': '植毛',
        'Tuft': '植毛',
        'Blister': '泡罩',
        'Online': '在线',
        'Warning': '警告',
        'Offline': '离线',
        'Total': '总计',
        'Flexible': '灵活',
        'Action': '操作',
        'System Settings': '系统设置',
        'Login': '登录',
        'Logout': '登出',
        'Monitoring': '监控',
        'Location Management': '位置管理',
        'Monitoring Dashboard': '监控仪表板',
        'Add New Device': '添加新设备',
        
        // Device Status
        'Devices online': '设备在线',
        'Devices breached thresholds': '设备超出阈值',
        'Devices disconnected': '设备断线',
        'Devices total': '总设备数',
        'Action total': '总操作数',
        
        // Modal Information
        'Information': '信息',
        'ID': '编号',
        'Process': '流程',
        'Mold Cavity': '模穴',
        'Actual Cavity': '实际穴数',
        'Capacity': '产量',
        'Efficiency': '效率',
        'Eff.requirement': '效率需求',
        'Current Cycle': '当前周期',
        'Target': '目标',
        'Upper Limit': '上限',
        'Lower Limit': '下限',
        'Total lost pcs': '总损失件数',
        'Lost time': '损失时间',
        'Total Count': '总计数',
        
        // Chart and Controls
        'Hourly Efficiency': '小时效率',
        '2 day ago': '2天前',
        'Yesterday': '昨天',
        'Today': '今天',
        'From dateline': '起始日期',
        'To dateline': '结束日期',
        'SEARCH': '搜索',
        'RESET': '重置',
        'Total Output': '总产出',
        'Average Cycle': '平均周期',
        'Export to Excel': '导出Excel',
        
        // Loss Report
        'Loss & Idle Time Report': '损失及空闲时间报告',
        
        // Action Modal
        '+ Add Action': '+ 添加操作',
        'Create new Action': '创建新操作',
        'Issue ID': '问题编号',
        'Issue Description': '问题描述',
        'Issue Type': '问题类型',
        'Actual Efficiency': '实际效率',
        'Actual Cycle': '实际周期',
        'Efficiency Required': '所需效率',
        'Priority': '优先级',
        'Due': '到期',
        'Creator': '创建者',
        'Action Plans': '操作计划',
        '+ Add Plan': '+ 添加计划',
        'Action ID': '操作编号',
        'Planned completion date': '计划完成日期',
        'Owner': '负责人',
        'Cancel': '取消',
        'Create Action': '创建操作',
        
        // Priority levels
        'low': '低',
        'medium': '中',
        'high': '高',
        'urgent': '紧急',
        
        // Common
        'Close': '关闭',
        'Save': '保存',
        'Delete': '删除',
        'Edit': '编辑',
        'View': '查看',
        'NO DATA': '无数据',
        'BACK': '返回',
        'VIEW MORE': '查看更多',
        'pcs_per_minute': '件/分钟',
        'rpm': '转/分钟',
        'brushes_per_cycle': '刷子/循环',
        'pcs': '件',
        'Shift Insert': '模具机器人',
        'Manual Insert': '手动模具',
        'Rotating': '旋转模具',
        'Shot': '模具',
        'hole/brush': '孔/刷子',
        'family': '类型',
        'chiller': '冷水机',
        'vacuum tank': '真空罐',
        'compressor': '压缩机',
        'end air pressure': '末端空气压力',
        'air tank': '空气罐',
        'workshop temperature': '车间温度',
        
        // Tabs
        'Issue': '问题',
        'Cooling Tower': '冷却塔',
        'Chiller': '冷水机',
        'Vacuum Tank': '真空罐',
        'Air Dryer': '空气干燥器',
        'Compressor': '压缩机',
        'End Air Pressure': '末端空气压力',
        'Air Tank': '空气罐',
        'Air Conditioner': '空调',
        'Workshop Temperature': '车间温度',
        
        // Table Headers
        'System': '系统',
        'Device ID': '设备ID',
        'Location': '位置',
        'Time': '时间',
        'Total Count (h)': '总计数 (小时)',
        'Status': '状态',
        'Connection': '连接',
        'Temperature': '温度',
        'Humidity': '湿度',
        'Pressure': '压力',
        'Actual': '实际',
        'Lower': '下限',
        'Target': '目标',
        'Upper': '上限',
        'Add': '添加',
        'Mold ID (Unique)': '模具编号（唯一）',
        'Family': '类型',
        'Model': '型号',
        'Manufacturer': '制造商',
        'Mfg. Date': '制造日期',
        'Capacity (1h)': '产能 (1小时)',
        'Unit': '单位',
        'Mold Type': '模具类型',
        'Efficiency Limit(%)': '效率限制(%)',
        'Limits (s) (LL/TG/UL)': '限制 (秒) (下限/目标/上限)',
        'Fre. Check Connected (s)': '频率检查连接 (秒)',
        'Fre. Check Limit (s)': '频率检查限制 (秒)',
        'Actions': '操作',
        'Injection ID': '压延机编号',
        'Frequency Check Connected (s)': '频率检查连接 (秒)',
        'Frequency Check Limit (s)': '频率检查限制 (秒)',
        'Tufting ID': '植毛编号',
        'Flex': '灵活',
        'Hole/Brush': '孔/刷子',
        'Limits (pcs) (LL/TG/UL)': '限制 (件) (下限/目标/上限)',
        'End-rounding ID': '磨圆机编号',
        'Blister ID': '泡罩编号',
        'Brushes/Cycle': '刷子/周期',
        'Limits (cycles) (LL/TG/UL)': '限制 (周期) (下限/目标/上限)',
        'Injection': '压延机',
        'End-rounding': '磨圆机',
        'Manufacturing Date': '制造日期',
        'Manual': '手动模具',
        'Auto': '自动模具',
        'Number of Cavities': '模穴数量',
        'Machine Type': '机器类型',
        'Lower Limit (s)': '下限 (秒)',
        'Target (s)': '目标 (秒)',
        'Upper Limit (s)': '上限 (秒)',
        
        // Pagination
        'Previous': '上一页',
        'Next': '下一页',
        'Page': '页面',
        'of': '/',
        
        // Modal
        'All tabs': '所有标签',
        'Search tab...': '搜索标签...',
        
        // Tooltips
        'Device is Disconnected': '设备已断线',
        'No issues detected': '未检测到问题',
        'No data available': '无可用数据',
        
        // New translations for JavaScript strings
        'DateTime': '日期时间',
        'Cavities': '模穴',
        'CycleTime': '周期时间 (秒)',
        'Output': '产量 (件)',
        'NoDataToExport': '无数据可导出。请先执行搜索。',
        'ErrorUnknownDeviceType': '未知设备类型，无法导出。',
        'FailedToExport': '无法导出数据。',
        'SelectBothDates': '请选择"起始"和"结束"日期。',
        'BrushesPerCycle': '刷子/周期',
        'CycleCount': '周期计数',
        'OutputPcsMin': '产量 (件/分钟)',
        'NoDataStructure': '无数据结构可用',
        'AverageOutput': '平均产量',
        'Cycle': '循环',
        'lost_pcs': '损失件(件)',
        'idle_breakdown': '停机/故障(秒)',
        'AverageCycle': '平均循环',
        'Select': '选择',
        'Type': '类型',
        'In progress': '进行中',
        'Complete': '完成',
        'Description Of Issue': '问题描述',
        'Created Date': '创建日期',
        'Planned Completion Date': '计划完成日期',
        'Machine': '机器',
        'Open': '打开',
        'Issue': '问题',
        'Action plan': '行动计划',
        'Created by': '创建者',
        'Created': '已创建',
        'Action Status': '行动状态',
        'Approval': '批准',
        'Manage': '管理',
        
        // Additional translations from previous JSON
        'EMS Dashboard': 'EMS仪表板',
        'Overall': '总体',
        'Running': '运行中',
        'Break Down': '故障',
        '07:00 ~ 19:00': '07:00 ~ 19:00',
        '19:00 ~ 07:00': '19:00 ~ 07:00',
        'Average': '平均',
        'Molding': '成型',
        'Blistering': '吸塑',
        'Machine ID': '机器ID',
        'Capacity / hr': '每小时产能',
        'EMS Monitoring': 'EMS监控',
        'Machine Details': '机器详情',
        'Efficiency Report': '效率报告',
        'Output Report': '产量报告',
        'Output Summary': '产量总结',
        'Output (pcs)': '产量（件）',
        'Lost (pcs)': '丢失（件）',
        'All Families': '所有家族',
        'SubTotal': '小计',
        'No data': '无数据',
        'Loading...': '加载中...',
        'No data found for this filter.': '未找到此筛选条件的数据。',
        'Failed to load data. Please try again.': '加载数据失败，请重试。',
        'Failed to load families:': '无法加载家族列表：',
        'Error loading': '加载错误',
        
        // Previous new translations
        'Mold ID': '模具ID',
        'Details': '详情',
        'Report': '报告',
        'From (VN)': '从',
        'To (VN)': '至',
        
        // New translation added
        'Approve Actions': '批准操作',
        
        // Previous new translations added
        'Update Location': '更新位置',
        'Location Name': '位置名称',
        'Description': '描述',
        'Add New Location': '添加新位置',
        
        // Previous new translations added
        'Welcome': '欢迎',
        'Data': '数据',
        'Frequency': '频率',
        'Check limit': '检查限制',
        'Temp (°C)': '温度 (°C)',
        'Humidity (%)': '湿度 (%)',
        'Pressure (kg/cm²)': '压力 (kg/cm²)',
        
        // Previous new translations added
        'Frequency (seconds)': '频率 (秒)',
        'System Type': '系统类型',
        'Update Device': '更新设备',
        'Parameters': '参数',
        'Temp Lower': '温度下限',
        'Temp Target': '温度目标',
        'Temp Upper': '温度上限',
        'Humid Lower': '湿度下限',
        'Humid Target': '湿度目标',
        'Humid Upper': '湿度上限',
        'Press Lower': '压力下限',
        'Press Target': '压力目标',
        'Press Upper': '压力上限',
        'Update': '更新',
        
        // New translations added
        'Username': '用户名',
        'Password': '密码',
        'LOGIN FMCS': '登录FMCS',
        'Logging out…': '正在登出…',
        'Signing out…': '正在退出…',
        'If you are not redirected automatically,': '如果您未被自动重定向，',
        'click here': '点击此处',
        
        // New translation added
        'All': '全部',
        
        // Login Messages
        'You need to log in to create an Action.': '您需要登录以创建操作。',
        'Username must be 4–20 characters and contain only letters, numbers, and underscores.': '用户名必须为4-20个字符，且仅包含字母、数字和下划线。',
        'Password must be at least 8 characters long.': '密码必须至少8个字符长。',
        'Incorrect username or password.': '用户名或密码不正确。',
        "Action plan...": "行动计划...",
        "Example: John Doe": "示例：约翰·多伊",
        "Loading families...": "正在加载产品类型...",
        "Delete this action?": "删除此操作？",
        "pending": "待处理",
        "approved": "已批准",
        "done": "完成",
        "plans": "计划",
        "plan": "计划",
        "Access denied: your account does not have permission to access System Settings.": "拒绝访问：您的账号没有权限进入系统设置。",
        "Could not load data. Server error.": "无法加载数据。服务器错误。",
        "No device selected. Please close and reopen the modal for a device.": "未选择设备。请关闭并重新打开设备窗口。",
        "Please add at least one full action plan (plan + date + owner).": "请至少新增一个完整的行动计划（计划 + 日期 + 负责人）。",
        "Device ID not found in the device popup.": "在设备窗口中找不到 Device ID。",
        "Device ID is required for hourly reports.": "每小时报告需要 Device ID。",
        "Device configuration not found or is incomplete.": "未找到设备配置或配置不完整。",
        "Invalid data source table name.": "数据源表名无效。",
        "Device ID and date range are required for device search.": "搜索设备需要 Device ID 和日期范围。",
        "Device configuration not found for device ID:": "未找到此 Device ID 的设备配置：",
        "Invalid date format. Expected 'Y-m-d H:i:s'.": "日期格式无效。应为 'Y-m-d H:i:s'。",
        "Failed to load data. Please try again.": "数据加载失败。请重试。",
        "Error loading": "加载错误",
        "No data found for the selected criteria.": "未找到符合条件的数据。",
        "Family not found in API list, will query with display name:": "在 API 列表中未找到 Family，将使用显示名称查询：",
        "Loading…": "加载中…",
        "Apply": "应用",
        "Progress": "进度",
        "ETA —": "预计时间 —",
        "— Select a family —": "— 选择一个 Family —",
        "Action already decided": "行动已决定",
        "No data": "没有数据",
        "Loading error:": "加载错误：",
        "Delete this action?": "要删除此行动吗？",
        "Successfully deleted": "删除成功",
        "Success": "成功",
        "Delete failed": "删除失败",
        "Delete error": "删除错误",
        "error": "错误",
        "Approve this action?": "要批准此行动吗？",
        "Reject this action?": "要拒绝此行动吗？",
        "Verification": "验证",
        "Approved successfully.": "批准成功。",
        "rejected.": "已拒绝。",
        "No action plans": "没有行动计划",
        "Load plans failed:": "加载计划失败：",
        "View actions": "查看行动",
        "You must sign in to create an action. Viewing actions is available to everyone.": "您必须登录才能创建行动。查看行动对所有人开放。",
        "Create new action for": "创建新行动于",
        "Device": "设备",
        "updated successfully": "更新成功",
        "Error: No device ID provided for deletion.": "错误：未提供要删除的设备 ID。",
        "Error: Frequency Check Limit must be greater than Frequency.": "错误：频率检查上限必须大于频率。",
        "Error: Please fill in all required information for update.": "错误：请填写所有必填的更新信息。",
        "Error: Location ID": "错误：位置 ID",
        "does not exist.": "不存在。",
        "added successfully.": "添加成功。",
        "Error: Please fill in all required device information.": "错误：请填写所有必填的设备信息。",
        "Device is Disconnected": "设备已断开连接",
        "Processing...": "处理中...",
        "An error occurred. Please try again.": "发生错误。请重试。",
        "Action not found": "未找到行动",
        "View all actions of": "查看所有行动于",
        "Auto from device — not editable": "来自设备自动生成 — 不可编辑",
        "You must sign in to create an action.": "您必须登录才能创建行动。",
        "Cannot verify login status.": "无法验证登录状态。",
        "Describe the issue...": "描述问题...",
        "Please add at least one Action Plan.": "请至少添加一个行动计划。",
        "Open actions": "打开的行动",
        "Are you sure you want to delete this action plan?": "您确定要删除此行动计划吗？",

        "Example: TGN001_IN": "示例: TGN001_IN",
        "Example: 60": "示例: 60",
        "Example: 180": "示例: 180",
        'Select system type': '选择系统类型',
        'Select location': '选择地点',
        'Factory Temperature': '工厂温度',
        'All tabs': '所有标签',
        'Example: Factory 1': '示例：工厂1',
        'Optional description': '可选描述',
        'Please fill out this field': '请填写此字段',
        "TOOLS": "工具",
        "todo": "待办事项",
        "-- Select issue type --": "-- 选择问题类型 --",
        "Factual data": "实际数据",
        "Please select Issue Type.": "请选择问题类型。",
        "Planned Date": "计划日期",
        "All action plans of": "所有行动计划于",
        "TOOLS": "工具",
        "todo": "待办事项",
        "-- Select issue type --": "-- 选择问题类型 --",
        "Factual data": "实际数据",
        "Please select Issue Type.": "请选择问题类型。",
        "Planned Date": "计划日期",
        "All action plans of": "所有行动计划于",
        "Are you sure you have completed this action?": "您确定已完成此行动吗？",
        "Add Plan": "添加计划",
        "Your account cannot create actions.": "您的账户无法创建操作。",
        "Connected": "已连接",
        "Disconnected": "已断开",
        "Audit Trail": "审计记录",
        "OTP error!": "OTP 错误！",
        "Creates": "创建",
        "Updates": "更新",
        "Deletes": "删除",
        "Total Actions": "操作总数",
        "Timestamp": "时间戳",
        "Reason": "原因",
        "Action Type": "操作类型",
        "All Actions": "全部操作",
        "Change date type": "更改日期类型",
        "Date Range": "日期范围",
        "Date": "日期",
        "Apply Filters": "应用筛选",
        "Reset Filters": "重置筛选",
        "Audit Diff": "审计差异",
        "Record #": "记录 #",
        "Close": "关闭",
        "Field": "字段",
        "Before": "之前",
        "After": "之后",
        "Timeline View": "时间线视图",
        "Table View": "表格视图",
        "Create": "创建",
        "Export CSV": "导出 CSV",
        "Filters": "筛选",
        "Track all system changes and modifications": "跟踪所有系统变更与修改",
        "View Diff": "查看差异",
        "Search by reason, user, ID...": "按原因、用户、ID 搜索..."
    }
};
// Translation Manager Class
class TranslationManager {
    constructor() {
        this.currentLang = localStorage.getItem('preferred_language') || 'en';
        this.translations = translations;
        this.init();
    }

    init() {
        this.createLanguageSelector();
        this.translatePage();
        this.observeMutations();
    }

    createLanguageSelector() {
        // Thử tìm #dropdown-menu trước, nếu không thấy thì tìm #header-dropdown
        let headerRight = document.querySelector('#dropdown-menu');
        let useLiTag = true;

        if (!headerRight) {
            headerRight = document.querySelector('#header-dropdown');
            useLiTag = false;
        }

        if (!headerRight) return;
        

        // Tạo language selector với thẻ li hoặc div tùy thuộc vào container
        const langSelector = document.createElement(useLiTag ? 'li' : 'div');
        langSelector.className = 'language-selector relative';
        langSelector.innerHTML = `
            <button id="lang-toggle" style="margin-left: 7px;" class="flex items-center space-x-1 text-gray-600 hover:text-gray-800">
                <i class="fas fa-globe w-4 h-4"></i>
                <span class="text-sm font-medium">${this.getLangDisplayName(this.currentLang)}</span>
                <i class="fas fa-chevron-down w-3 h-3"></i>
            </button>
            <div id="lang-dropdown" class="absolute right-0 mt-2 w-40 bg-white rounded-md shadow-lg border border-gray-200 z-50 hidden">
                <div class="py-1">
                    <a href="#" data-lang="en" class="lang-option flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <span class="mr-2">🇺🇸</span>English
                    </a>
                    <a href="#" data-lang="vi" class="lang-option flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <span class="mr-2">🇻🇳</span>Tiếng Việt
                    </a>
                    <a href="#" data-lang="zh-TW" class="lang-option flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <span class="mr-2">🇹🇼</span>繁體中文
                    </a>
                    <a href="#" data-lang="zh-CN" class="lang-option flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <span class="mr-2">🇨🇳</span>简体中文
                    </a>
                </div>
            </div>
        `;

        // Chèn trước datetime-display nếu có, nếu không thì append vào headerRight
        const datetimeDisplay = headerRight.querySelector('.datetime-display');
        if (datetimeDisplay) {
            headerRight.insertBefore(langSelector, datetimeDisplay);
        } else {
            headerRight.appendChild(langSelector);
        }

        // Thêm event listeners
        this.addLanguageSelectorEvents();
    }

    addLanguageSelectorEvents() {
        const langToggle = document.getElementById('lang-toggle');
        const langDropdown = document.getElementById('lang-dropdown');
        const langOptions = document.querySelectorAll('.lang-option');

        // Toggle dropdown
        langToggle?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            langDropdown?.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!langToggle?.contains(e.target) && !langDropdown?.contains(e.target)) {
                langDropdown?.classList.add('hidden');
            }
        });

        // Language selection
        langOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const newLang = e.currentTarget.getAttribute('data-lang');
                this.changeLanguage(newLang);
                langDropdown?.classList.add('hidden');
            });
        });
    }

    getLangDisplayName(lang) {
        const names = {
            'en': 'EN',
            'vi': 'VI',
            'zh-TW': '繁體',
            'zh-CN': '简体'
        };
        return names[lang] || 'EN';
    }

    changeLanguage(newLang) {
        if (newLang === this.currentLang) return;
        localStorage.setItem('preferred_language', newLang);
        window.location.reload(true); // Force reload toàn trang, bỏ qua cache (tương tự Ctrl+Shift+R)
    }

    translate(key, lang = null) {
        const targetLang = lang || this.currentLang;
        return this.translations[targetLang]?.[key] || 
               this.translations['en'][key] || 
               key;
    }

    // NEW: Parse translate(key) syntax for placeholders
    parseTranslateSyntax(value) {
        const translateMatch = value.match(/^translate\(['"]?(.+?)['"]?\)$/);
        if (translateMatch) {
            const key = translateMatch[1].trim();
            return this.translate(key);
        }
        return this.translate(value);
    }

    translatePage() {
        // Translate elements with data-translate attribute
        document.querySelectorAll('[data-translate]').forEach(element => {
            const key = element.getAttribute('data-translate');
            const translated = this.translate(key);
            
            if (element.tagName === 'INPUT' && (element.type === 'button' || element.type === 'submit')) {
                element.value = translated;
            } else if (element.tagName === 'INPUT' && element.placeholder) {
                element.placeholder = translated;
            } else {
                element.textContent = translated;
            }
        });

        // Translate elements with data-translate-dynamic attribute
        document.querySelectorAll('[data-translate-dynamic]').forEach(element => {
            const field = element.getAttribute('data-translate-dynamic');
            const originalText = element.textContent.trim();
            if (originalText !== '-' && originalText !== '') {
                const translated = this.translate(originalText);
                element.textContent = translated;
            }
        });

        // NEW: Translate placeholders with translate(key) syntax
        document.querySelectorAll('input[placeholder]').forEach(element => {
            const originalPlaceholder = element.getAttribute('placeholder');
            const translated = this.parseTranslateSyntax(originalPlaceholder);
            element.setAttribute('placeholder', translated);
        });

        // Translate common elements by text content
        this.translateByTextContent();
        
        // Translate tooltips
        this.translateTooltips();
    }

    translateByTextContent() {
        // Get all text nodes and translate them
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: (node) => {
                    // Skip script and style tags
                    const parent = node.parentElement;
                    if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE') {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Only process text nodes with meaningful content
                    return node.textContent.trim().length > 0 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                }
            }
        );

        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        textNodes.forEach(textNode => {
            const originalText = textNode.textContent.trim();
            // Check for translationManager.translate('key') pattern
            const translateMatch = originalText.match(/translationManager\.translate\('([^']+)'\)(.*)/);
            if (translateMatch) {
                const key = translateMatch[1];
                const postfix = translateMatch[2].trim();
                const translated = this.translate(key);
                textNode.textContent = `${translated} ${postfix}`;
            } else {
                // Fallback to standard translation for non-matching text
                const translated = this.translate(originalText);
                if (translated !== originalText) {
                    textNode.textContent = translated;
                }
            }
        });
    }

    translateTooltips() {
        document.querySelectorAll('[title]').forEach(element => {
            const originalTitle = element.getAttribute('title');
            const translated = this.translate(originalTitle);
            if (translated !== originalTitle) {
                element.setAttribute('title', translated);
            }
        });
    }

    // Method to translate dynamic data from server
    translateDynamicData(data, fieldsToTranslate = []) {
        if (this.currentLang === 'en') return data;

        if (Array.isArray(data)) {
            return data.map(item => this.translateDynamicData(item, fieldsToTranslate));
        }

        if (typeof data === 'object' && data !== null) {
            const translated = { ...data };
            
            fieldsToTranslate.forEach(field => {
                if (translated[field]) {
                    translated[field] = this.translate(translated[field]);
                }
            });

            // Auto-translate common field names
            const commonFields = ['status', 'type', 'priority', 'category', 'state'];
            commonFields.forEach(field => {
                if (translated[field] && !fieldsToTranslate.includes(field)) {
                    translated[field] = this.translate(translated[field]);
                }
            });

            return translated;
        }

        return this.translate(data);
    }

    observeMutations() {
        // Observe DOM changes to translate dynamically added content
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            this.translateElement(node);
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    translateElement(element) {
        // Translate newly added element
        if (element.hasAttribute && element.hasAttribute('data-translate')) {
            const key = element.getAttribute('data-translate');
            const translated = this.translate(key);
            element.textContent = translated;
        }

        // Translate children
        element.querySelectorAll?.('[data-translate]').forEach(child => {
            const key = child.getAttribute('data-translate');
            const translated = this.translate(key);
            child.textContent = translated;
        });

        // Translate dynamic text content in children
        element.querySelectorAll?.('[data-translate-dynamic]').forEach(child => {
            const field = child.getAttribute('data-translate-dynamic');
            const originalText = child.textContent.trim();
            if (originalText !== '-' && originalText !== '') {
                const translated = this.translate(originalText);
                child.textContent = translated;
            }
        });

        // NEW: Translate placeholders with translate(key) syntax
        if (element.tagName === 'INPUT' && element.hasAttribute('placeholder')) {
            const originalPlaceholder = element.getAttribute('placeholder');
            const translated = this.parseTranslateSyntax(originalPlaceholder);
            element.setAttribute('placeholder', translated);
        }
        element.querySelectorAll?.('input[placeholder]').forEach(child => {
            const originalPlaceholder = child.getAttribute('placeholder');
            const translated = this.parseTranslateSyntax(originalPlaceholder);
            child.setAttribute('placeholder', translated);
        });

        // Handle translationManager.translate('key') pattern in text nodes of this element
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: (node) => {
                    const parent = node.parentElement;
                    if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE') {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return node.textContent.trim().length > 0 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                }
            }
        );

        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        textNodes.forEach(textNode => {
            const originalText = textNode.textContent.trim();
            const translateMatch = originalText.match(/translationManager\.translate\('([^']+)'\)(.*)/);
            if (translateMatch) {
                const key = translateMatch[1];
                const postfix = translateMatch[2].trim();
                const translated = this.translate(key);
                textNode.textContent = `${translated} ${postfix}`;
            }
        });
    }
}

// Initialize translation manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.translationManager = new TranslationManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TranslationManager;
}